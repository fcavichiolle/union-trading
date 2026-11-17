<?php

namespace Tests\Feature;

use App\Models\Armazem;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Embarque;
use App\Models\Qualidade;
use App\Models\Role;
use App\Models\Transportadora;
use App\Models\User;
use App\Services\ProgramacaoEstufagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Programação de estufagem: o PDF que o armazém imprime e o e-mail que a
 * logística manda para armazém e transportadora. Era montado à mão a
 * partir da planilha de embarques.
 */
class ProgramacaoEstufagemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $financeiro;
    private Armazem $saag;
    private Embarque $embarque;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-17 10:00:00');
        config(['exportacao.copia_fixa' => '']);

        $admin = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $fin = Role::create(['slug' => 'financeiro', 'nome' => 'Financeiro']);
        $this->admin = User::create([
            'role_id' => $admin->id, 'name' => 'Admin', 'email' => 'admin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
        $this->financeiro = User::create([
            'role_id' => $fin->id, 'name' => 'Fin', 'email' => 'fin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);

        $cliente = Cliente::create(['nome' => 'COMPRADOR EXEMPLO', 'endereco' => 'Genebra']);
        $qualidade = Qualidade::create(['descricao' => 'NY 2/3 SCREEN 17/18 FINE CUP']);

        // Armazém semeado pela migration; o que falta é a lista de e-mails.
        $this->saag = Armazem::where('nome', 'SAAG')->firstOrFail();
        $this->saag->update(['emails_programacao' => 'saag@armazem.example.com; cafe@armazem.example.com']);

        Transportadora::create(['nome' => 'TRANSNORTE', 'emails' => 'exportacao.vazios@transportadora.example.com']);

        $contrato = Contrato::create([
            'numero_ut' => '5942', 'data_contrato' => '2026-08-12',
            'cliente_id' => $cliente->id, 'cliente_nome' => $cliente->nome, 'cliente_endereco' => $cliente->endereco,
            'qualidade_id' => $qualidade->id, 'qualidade_descricao' => $qualidade->descricao,
            'buyer_ref' => 'CN 0001 TESTE-0001', 'standard' => '1718_FC',
            'tipo_cafe' => 'ARABICA', 'certificado' => 'SEM_CERT', 'quantidade_kg' => 38400,
            'tipo_container' => '40', 'embalagem' => 'Jute Bags', 'incoterms' => 'FOB', 'porto' => 'SANTOS',
            'embarque_mes' => '2026-11-01', 'created_by' => $this->admin->id,
        ]);

        $this->embarque = new Embarque([
            'mes_faturamento' => '2026-11-01', 'quantidade_kg' => 38400, 'status' => 'PROGRAMADO',
            'armazem_id' => $this->saag->id, 'transportadora' => 'Transnorte',
            'data_fumigacao' => '2026-11-13', 'fumigacao_empresa' => 'Nikkey',
            'fumigacao_tipo' => 'CÂMARA DE LONA', 'fumigacao_tempo' => '120H',
            'data_estufagem' => '2026-11-19', 'deadline_carga' => '2026-11-21',
            'destino' => 'ANTWERP', 'etd' => '2026-11-20', 'navio' => 'MSC ADELE',
            'booking' => '999TESTE0001 - MSC', 'ico' => '1007 + 1008', 'pilha' => 'PI8036/26',
            'instrucoes' => 'É NECESSÁRIO UTILIZAR KIT FORRAÇÃO',
        ]);
        $this->embarque->contrato()->associate($contrato);
        $this->embarque->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function programacao(): ProgramacaoEstufagem
    {
        return ProgramacaoEstufagem::de($this->embarque->fresh(), $this->admin);
    }

    /** O assunto repete o formato que a equipe já usa nos e-mails. */
    public function test_assunto_no_formato_da_equipe(): void
    {
        $this->assertSame(
            'PROGRAMAÇÃO ESTUFAGEM: UT 5942 | CN 0001 TESTE-0001 - BOOKING: 999TESTE0001 - MSC - ARMAZEM: SAAG',
            $this->programacao()->assunto()
        );
    }

    /**
     * Destinatários = armazém de saída + transportadora. A transportadora é
     * achada pelo NOME gravado no embarque, sem diferenciar maiúsculas
     * ("Transnorte" no embarque, "TRANSNORTE" no cadastro).
     */
    public function test_destinatarios_sao_o_armazem_e_a_transportadora(): void
    {
        $p = $this->programacao();

        $this->assertSame(
            ['saag@armazem.example.com', 'cafe@armazem.example.com', 'exportacao.vazios@transportadora.example.com'],
            $p->para
        );
        $this->assertTrue($p->enviavel());
        $this->assertSame([], $p->avisos);
    }

    /** Cópia fixa da casa entra; quem já é destinatário não repete. */
    public function test_copia_fixa_sem_repetir_destinatario(): void
    {
        config(['exportacao.copia_fixa' => 'SAAG@armazem.example.com; logistica@utrading.example.com']);

        $this->assertSame(['logistica@utrading.example.com'], $this->programacao()->copia);
    }

    public function test_avisa_o_que_falta_para_enviar(): void
    {
        $this->saag->update(['emails_programacao' => null]);
        $this->embarque->update(['transportadora' => 'RODOCAFE', 'data_estufagem' => null]);

        $p = $this->programacao();
        $avisos = implode(' ', $p->avisos);

        $this->assertFalse($p->enviavel());
        $this->assertStringContainsString('SAAG não tem e-mails', $avisos);
        $this->assertStringContainsString('RODOCAFE', $avisos);
        $this->assertStringContainsString('data de estufagem', $avisos);
    }

    public function test_mailto_separa_por_ponto_e_virgula(): void
    {
        $mailto = $this->programacao()->mailto();

        $this->assertStringStartsWith('mailto:saag@armazem.example.com;cafe@armazem.example.com;exportacao.vazios@transportadora.example.com?', $mailto);
        $this->assertStringContainsString('subject=PROGRAMA%C3%87%C3%83O%20ESTUFAGEM', $mailto);
        $this->assertStringContainsString('Estufagem%3A%2019%2F11%2F2026', $mailto);
    }

    /** O PDF traz a tabela de datas, as instruções e a tabela da fumigação. */
    public function test_pdf_traz_a_grade_as_instrucoes_e_a_fumigacao(): void
    {
        $html = view('embarques.programacao-pdf', [
            'programacao' => $this->programacao(), 'geradoPor' => 'Admin',
        ])->render();

        $esperado = [
            'UT 5942 | CN 0001 TESTE-0001', '19/11/2026', '21/11/2026', 'Transnorte', 'ANTWERP', 'MSC ADELE',
            '999TESTE0001 - MSC', '1007 + 1008', 'PI8036/26', 'Jute Bags',
            'É NECESSÁRIO UTILIZAR KIT FORRAÇÃO',
            // tabela da fumigação
            'Nikkey', 'CÂMARA DE LONA', '120H', 'Total de 640 sacas', '38.400', 'N/A',
        ];

        foreach ($esperado as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
    }

    /** Sem fumigação agendada, o bloco da fumigação não aparece. */
    public function test_pdf_sem_fumigacao_nao_mostra_o_bloco(): void
    {
        $this->embarque->update(['data_fumigacao' => null]);

        $html = view('embarques.programacao-pdf', [
            'programacao' => $this->programacao(), 'geradoPor' => 'Admin',
        ])->render();

        $this->assertStringNotContainsString('Tipo de fumigação', $html);
        $this->assertStringNotContainsString('Nikkey', $html);
    }

    public function test_baixa_o_pdf_da_programacao(): void
    {
        $resposta = $this->actingAs($this->admin)
            ->get(route('embarques.programacao', $this->embarque))
            ->assertOk();

        $this->assertSame('application/pdf', $resposta->headers->get('content-type'));
        $this->assertStringContainsString('Programacao_UT_5942_19-11-2026.pdf', $resposta->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    public function test_perfil_sem_permissao_nao_gera_a_programacao(): void
    {
        $this->actingAs($this->financeiro)
            ->get(route('embarques.programacao', $this->embarque))
            ->assertForbidden();
    }

    /** O bloco com os botões aparece na edição; no embarque novo, não. */
    public function test_tela_do_embarque_oferece_pdf_e_email(): void
    {
        $this->actingAs($this->admin)
            ->get(route('embarques.edit', $this->embarque))
            ->assertOk()
            ->assertSee('Baixar PDF da programação')
            ->assertSee('Abrir e-mail no Outlook')
            ->assertSee('mailto:saag@armazem.example.com', false);

        $this->actingAs($this->admin)
            ->get(route('embarques.create', $this->embarque->contrato))
            ->assertOk()
            ->assertDontSee('Baixar PDF da programação');
    }

    public function test_formulario_grava_os_campos_da_programacao(): void
    {
        $this->actingAs($this->admin)
            ->put(route('embarques.update', $this->embarque), [
                'mes_faturamento' => '2026-11', 'quantidade_kg' => '38400', 'status' => 'PROGRAMADO',
                'ico' => '112 + 113', 'fumigacao_empresa' => 'Nikkey', 'fumigacao_tipo' => 'Câmara de lona',
                'fumigacao_tempo' => '72H', 'permit_import' => 'PI-123', 'instrucoes' => 'Enviar fotos da estufagem.',
            ])
            ->assertRedirect();

        $e = $this->embarque->fresh();
        $this->assertSame('112 + 113', $e->ico);
        $this->assertSame('72H', $e->fumigacao_tempo);
        $this->assertSame('PI-123', $e->permit_import);
        $this->assertSame('Enviar fotos da estufagem.', $e->instrucoes);
    }

    public function test_cadastro_de_transportadoras(): void
    {
        $eu = $this->actingAs($this->admin);

        $eu->get(route('admin.transportadoras.index'))->assertOk()->assertSee('TRANSNORTE');

        $eu->post(route('admin.transportadoras.store'), ['nome' => 'TRANSAÇUCENA', 'emails' => 'op@transportes.example.com; isto-nao-e-email'])
            ->assertSessionHasErrors('emails');

        $eu->post(route('admin.transportadoras.store'), ['nome' => 'TRANSAÇUCENA', 'emails' => 'op@transportes.example.com'])
            ->assertRedirect(route('admin.transportadoras.index'));
        $this->assertSame(['op@transportes.example.com'], Transportadora::porNome('transaçucena')->listaDeEmails());

        $eu->post(route('admin.transportadoras.store'), ['nome' => 'TRANSNORTE'])->assertSessionHasErrors('nome');
    }

    public function test_armazem_guarda_os_emails_da_programacao(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.armazens.update', $this->saag), [
                'nome' => 'SAAG', 'cidade' => $this->saag->cidade, 'estado' => $this->saag->estado,
                'emails_programacao' => 'faturamento@armazem.example.com; silo@armazem.example.com',
            ])
            ->assertRedirect();

        $this->assertSame(['faturamento@armazem.example.com', 'silo@armazem.example.com'], $this->saag->fresh()->emailsProgramacao());

        $this->actingAs($this->admin)->get(route('admin.armazens.index'))
            ->assertOk()
            ->assertSee('faturamento@armazem.example.com');
    }
}
