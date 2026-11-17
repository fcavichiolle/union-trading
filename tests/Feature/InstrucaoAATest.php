<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Corretora;
use App\Models\Fixacao;
use App\Models\Qualidade;
use App\Models\Role;
use App\Models\User;
use App\Services\InstrucaoAA;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Instrução de AA: o e-mail que a mesa mandava pela planilha
 * `AA instructions v2.xlsm` e que agora sai da própria fixação.
 *
 * O que está guardado aqui é o que a planilha fazia e que é fácil de
 * quebrar sem perceber: o preço ser o LEVEL (e não o preço da tranche),
 * a separação por cliente numa fixação em grupo, e os destinatários
 * virem da nossa corretora + do cliente.
 */
class InstrucaoAATest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Cliente $alfa;
    private Qualidade $qualidade;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);

        $this->admin = User::create([
            'role_id' => $admin->id,
            'name' => 'Marina Souza Lima',
            'email' => 'logistica@utrading.example.com',
            'password' => Hash::make('x'),
            'force_password_change' => false,
            'active' => true,
            'cargo' => 'Assistente de logística',
            'cargo_en' => 'Logistics Assistant',
            'telefone' => '+55 (11) 4000-0000',
            'celular' => '+55 (11) 90000-0000',
        ]);

        $this->alfa = Cliente::create([
            'nome' => 'Alfa',
            'endereco' => 'Madrid – Spain',
            'emails_copia' => 'compras@cliente.example.com',
        ]);

        $this->qualidade = Qualidade::create(['descricao' => 'CAFÉ ARÁB NAT BRASIL 14/16']);

        // A corretora nossa já vem semeada pela migration de corretoras; o
        // que falta é a lista de destinatários, que é cadastro novo.
        Corretora::nossas()->where('nome', 'StoneX East Coast')->update([
            'emails_para' => 'Mesa.Cafe@Corretora.example.com; Brasil@Corretora.example.com',
            'emails_copia' => 'diretoria@utrading.example.com',
        ]);

        // Sem cópia fixa por padrão: quem testa isso liga na hora. Senão o
        // resultado mudaria conforme o `.env` de quem roda os testes.
        config(['aa.copia_fixa' => '']);
    }

    private function contrato(string $ut, ?Cliente $cliente = null, array $overrides = []): Contrato
    {
        $cliente ??= $this->alfa;

        return Contrato::create(array_merge([
            'numero_ut' => $ut, 'data_contrato' => '2026-08-05',
            'cliente_id' => $cliente->id, 'cliente_nome' => $cliente->nome, 'cliente_endereco' => $cliente->endereco,
            'qualidade_id' => $this->qualidade->id, 'qualidade_descricao' => $this->qualidade->descricao,
            'buyer_ref' => 'ECT 3980.26 | P51492',
            'tipo_cafe' => 'ARABICA', 'certificado' => 'RFA', 'quantidade_kg' => 108000,
            'tipo_container' => '40', 'embalagem' => 'Bulk Liner', 'incoterms' => 'FOB', 'porto' => 'SANTOS',
            'diferencial' => '-16.00', 'created_by' => $this->admin->id,
        ], $overrides));
    }

    private function fixar(Contrato $contrato, array $overrides = []): Fixacao
    {
        return Fixacao::create(array_merge([
            'contrato_id' => $contrato->id,
            'grupo' => 'a1b2c3d4-0000-4000-8000-000000000001',
            'corretora' => 'StoneX East Coast',
            'broker_cliente' => 'Stonex Miami',
            'tela' => 'Z6',
            'lotes' => 5,
            'level' => '271.25',
            'diferencial' => '-16.00',
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    public function test_corpo_repete_o_formato_da_planilha(): void
    {
        $fixacao = $this->fixar($this->contrato('5978'));

        $instrucao = InstrucaoAA::deFixacao($fixacao->load('contrato.cliente'), $this->admin);
        $corpo = $instrucao->corpo();

        $this->assertSame('AA UNION TRADING X Alfa', $instrucao->assunto());
        $this->assertStringContainsString("FROM: UNION TRADING\nTO: Alfa", $corpo);
        $this->assertStringContainsString('Broker: Stonex Miami', $corpo);
        $this->assertStringContainsString('Lots: 5', $corpo);
        $this->assertStringContainsString('Commodity/Month: KCZ6', $corpo);
        $this->assertStringContainsString("Contract(s):\nUT 5978 / ECT 3980.26 | P51492", $corpo);
    }

    /**
     * O AA se faz CONTRA A BOLSA: vale o level, não o preço da tranche
     * (level + diferencial), que é o preço do físico.
     */
    public function test_price_e_o_level_e_nao_o_preco_da_tranche(): void
    {
        $fixacao = $this->fixar($this->contrato('5978'));

        $this->assertEqualsWithDelta(255.25, (float) $fixacao->preco, 0.001); // 271,25 - 16
        $this->assertStringContainsString(
            'Price: 271,25',
            InstrucaoAA::deFixacao($fixacao->load('contrato.cliente'), $this->admin)->corpo()
        );
    }

    /**
     * O e-mail é sempre "Union X um cliente". Fixar de uma vez contratos de
     * clientes diferentes tem de gerar uma instrução para cada, cada uma só
     * com os contratos dela — na planilha isso era separado na mão.
     */
    public function test_fixacao_em_grupo_gera_uma_instrucao_por_cliente(): void
    {
        $beta = Cliente::create(['nome' => 'Beta', 'endereco' => 'Genebra – Suíça']);

        $this->fixar($this->contrato('5961'), ['lotes' => 6]);
        $this->fixar($this->contrato('5977'), ['lotes' => 4]);
        $this->fixar($this->contrato('5942', $beta), ['lotes' => 2]);

        $instrucoes = InstrucaoAA::deFixacoes(Fixacao::with('contrato.cliente')->get(), $this->admin);

        $this->assertCount(2, $instrucoes);

        $porCliente = collect($instrucoes)->keyBy('cliente');

        // Os dois contratos da Alfa entram na MESMA instrução, e os lotes somam.
        $this->assertSame(10, $porCliente['Alfa']->lotes);
        $this->assertStringContainsString('UT 5961', $porCliente['Alfa']->corpo());
        $this->assertStringContainsString('UT 5977', $porCliente['Alfa']->corpo());
        $this->assertStringNotContainsString('UT 5942', $porCliente['Alfa']->corpo());

        $this->assertSame(2, $porCliente['Beta']->lotes);
        $this->assertStringNotContainsString('UT 5961', $porCliente['Beta']->corpo());
    }

    public function test_destinatarios_somam_a_corretora_e_o_cliente(): void
    {
        $instrucao = InstrucaoAA::deFixacao(
            $this->fixar($this->contrato('5978'))->load('contrato.cliente'),
            $this->admin
        );

        $this->assertSame(['Mesa.Cafe@Corretora.example.com', 'Brasil@Corretora.example.com'], $instrucao->para);
        $this->assertSame(['diretoria@utrading.example.com', 'compras@cliente.example.com'], $instrucao->copia);
        $this->assertTrue($instrucao->enviavel());
    }

    /**
     * Fixar com corretora de nome longo. `fixacoes.corretora` nasceu com 30
     * caracteres (época dos códigos 'STONEX'/'ICAP') e continuou assim quando
     * as corretoras viraram cadastro e a fixação passou a gravar o NOME:
     * escolher "ICAP Corporates LLC (Hedgepoint)" (32) derrubava a tela com
     * "Data too long for column 'corretora'". Só a StoneX, curta, cabia.
     *
     * ATENÇÃO — este teste NÃO pega a regressão. O SQLite daqui declara a
     * coluna como `varchar` **sem tamanho** (o Laravel não emite o limite
     * nesse driver) e aceita qualquer string; o estouro só existe no MySQL, e
     * nem dá para conferir o tamanho pelo schema, porque no SQLite ele não
     * está gravado. O que segura de verdade é a migration
     * `..._alarga_corretora_nas_fixacoes`. O teste fica como documentação do
     * caso e para garantir que nada no caminho (validação, request) volte a
     * limitar o nome.
     */
    public function test_corretora_de_nome_longo_pode_ser_fixada(): void
    {
        $icap = 'ICAP Corporates LLC (Hedgepoint)';
        $this->assertGreaterThan(30, mb_strlen($icap));

        Corretora::nossas()->where('nome', $icap)->update([
            'emails_para' => 'mesa@outra-corretora.example.com',
        ]);

        $contrato = $this->contrato('5978');

        $this->actingAs($this->admin)
            ->post(route('ny.fixacoes.store'), [
                'contratos' => [$contrato->id],
                'corretora' => $icap,
                'broker_cliente' => 'Macquarie futures broker LLC',
                'tela' => 'Z6',
                'lotes' => 2,
                'level' => '288.00',
                'diferenciais' => [$contrato->id => '-66.00'],
            ])
            ->assertRedirect(route('ny.index'));

        $this->assertSame($icap, Fixacao::first()->corretora);
    }

    /** A cópia fixa da casa (config/aa.php) entra em toda instrução. */
    public function test_copia_fixa_entra_em_todas_as_instrucoes(): void
    {
        config(['aa.copia_fixa' => 'posicao@utrading.example.com']);

        $instrucao = InstrucaoAA::deFixacao(
            $this->fixar($this->contrato('5978'))->load('contrato.cliente'),
            $this->admin
        );

        $this->assertSame(
            ['diretoria@utrading.example.com', 'compras@cliente.example.com', 'posicao@utrading.example.com'],
            $instrucao->copia
        );
    }

    /**
     * Quem já é destinatário não entra também na cópia — receberia a mesma
     * instrução duas vezes. Vale ignorando maiúsculas/minúsculas, porque a
     * lista da corretora e o `.env` são digitados por pessoas diferentes.
     */
    public function test_quem_esta_no_para_nao_se_repete_na_copia(): void
    {
        config(['aa.copia_fixa' => 'MESA.CAFE@corretora.example.com; posicao@utrading.example.com']);

        $instrucao = InstrucaoAA::deFixacao(
            $this->fixar($this->contrato('5978'))->load('contrato.cliente'),
            $this->admin
        );

        $this->assertContains('Mesa.Cafe@Corretora.example.com', $instrucao->para);
        $this->assertNotContains('MESA.CAFE@corretora.example.com', $instrucao->copia);
        $this->assertContains('posicao@utrading.example.com', $instrucao->copia);
    }

    /**
     * Sem destinatário não há e-mail — a planilha dava um MsgBox; aqui o
     * botão não aparece e o aviso diz onde cadastrar.
     */
    public function test_avisa_quando_a_corretora_nao_tem_email_cadastrado(): void
    {
        Corretora::nossas()->where('nome', 'StoneX East Coast')->update(['emails_para' => null]);

        $instrucao = InstrucaoAA::deFixacao(
            $this->fixar($this->contrato('5978'))->load('contrato.cliente'),
            $this->admin
        );

        $this->assertFalse($instrucao->enviavel());
        $this->assertStringContainsString('não tem destinatários cadastrados', implode(' ', $instrucao->avisos()));
    }

    /** Londres não usa o código da tela como está: `Sep_2026` vira `RCU6`. */
    public function test_commodity_month_traduz_a_tela_de_londres(): void
    {
        $this->assertSame('KCZ6', Contrato::commodityDaTela('Z6'));
        $this->assertSame('KCH7', Contrato::commodityDaTela('H7'));
        $this->assertSame('RCU6', Contrato::commodityDaTela('Sep_2026'));
        $this->assertSame('RCX7', Contrato::commodityDaTela('Nov_2027'));
        $this->assertNull(Contrato::commodityDaTela(null));
    }

    public function test_mailto_leva_destinatarios_assunto_e_corpo(): void
    {
        $mailto = InstrucaoAA::deFixacao(
            $this->fixar($this->contrato('5978'))->load('contrato.cliente'),
            $this->admin
        )->mailto();

        // Ponto e vírgula, NÃO vírgula: o Outlook só separa por vírgula se a
        // opção correspondente estiver ligada, e ela vem desligada — com
        // vírgula ele junta tudo num destinatário só.
        $this->assertStringStartsWith('mailto:Mesa.Cafe@Corretora.example.com;Brasil@Corretora.example.com?', $mailto);
        $this->assertStringContainsString('cc=diretoria@utrading.example.com;compras@cliente.example.com', $mailto);
        $this->assertStringNotContainsString('example.com,', $mailto);
        $this->assertStringContainsString('subject=AA%20UNION%20TRADING%20X%20Alfa', $mailto);
        // Quebra de linha no corpo precisa ir como %0D%0A, senão o Outlook
        // abre tudo numa linha só.
        $this->assertStringContainsString('%0D%0A', $mailto);
    }

    /** A assinatura é de quem está mandando, não uma fixa dentro do código. */
    public function test_assinatura_e_do_usuario_logado(): void
    {
        $corpo = InstrucaoAA::deFixacao(
            $this->fixar($this->contrato('5978'))->load('contrato.cliente'),
            $this->admin
        )->corpo();

        $this->assertStringContainsString(
            "Atenciosamente,\n\nMarina Souza Lima\nAssistente de logística\nLogistics Assistant",
            $corpo
        );
        $this->assertStringContainsString('logistica@utrading.example.com', $corpo);
        $this->assertStringContainsString('www.utrading.com.br', $corpo);
    }

    /** Usuário sem cargo/telefone assina só com nome, e-mail e site. */
    public function test_assinatura_pula_os_campos_em_branco(): void
    {
        $this->admin->update(['cargo' => null, 'cargo_en' => null, 'telefone' => null, 'celular' => null]);

        $this->assertSame(
            "Atenciosamente,\n\nMarina Souza Lima\n\nlogistica@utrading.example.com\nwww.utrading.com.br",
            $this->admin->fresh()->assinaturaEmail()
        );
    }

    /** Registrar a fixação já devolve a instrução pronta na tela. */
    public function test_tela_de_fixacao_oferece_a_instrucao_depois_de_registrar(): void
    {
        $contrato = $this->contrato('5978');

        $resposta = $this->actingAs($this->admin)->post(route('ny.fixacoes.store'), [
            'contratos' => [$contrato->id],
            'corretora' => 'StoneX East Coast',
            'broker_cliente' => 'Stonex Miami',
            'tela' => 'Z6',
            'lotes' => 2,
            'level' => '271.25',
            'diferenciais' => [$contrato->id => '-16.00'],
        ]);

        $resposta->assertRedirect(route('ny.index'));
        $resposta->assertSessionHas('aa');

        $aa = session('aa');
        $this->assertCount(1, $aa);
        $this->assertSame('Alfa', $aa[0]['cliente']);
        $this->assertTrue($aa[0]['enviavel']);
        $this->assertStringContainsString('Commodity/Month: KCZ6', $aa[0]['corpo']);

        // E a tranche nasce marcada com o grupo do envio, para remontar depois.
        $this->assertNotNull(Fixacao::first()->grupo);
    }

    /**
     * O botão chega à TELA, não só à sessão. Flash não sobrevive a uma
     * requisição separada nos testes (GOTCHA 7), por isso o
     * `followingRedirects()`: ele renderiza o destino no mesmo ciclo.
     */
    public function test_botao_de_abrir_o_outlook_aparece_na_tela(): void
    {
        $contrato = $this->contrato('5978');

        $html = $this->actingAs($this->admin)
            ->from(route('ny.index'))
            ->followingRedirects()
            ->post(route('ny.fixacoes.store'), [
                'contratos' => [$contrato->id],
                'corretora' => 'StoneX East Coast',
                'tela' => 'Z6',
                'lotes' => 2,
                'level' => '271.25',
                'diferenciais' => [$contrato->id => '-16.00'],
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Abrir no Outlook — Alfa', $html);
        $this->assertStringContainsString('mailto:Mesa.Cafe@Corretora.example.com', $html);
        $this->assertStringContainsString('Copiar texto', $html);
        // Dispensar o aviso depois de enviado. Nasce `hidden`: só serve com JS.
        $this->assertStringContainsString('Instrução enviada', $html);
        $this->assertStringContainsString('js-aa-enviada', $html);
        // A dica do Ctrl+K fica na tela: o `mailto:` não resolve os
        // destinatários sozinho e sem o atalho a pessoa clica um a um.
        $this->assertStringContainsString('Verificar Nomes', $html);
    }

    /**
     * As telas de cadastro que alimentam o e-mail abrem e trazem os campos
     * novos. Blade tem armadilhas que só aparecem ao renderizar (diretiva
     * colada numa palavra não compila), então vale a checagem.
     */
    public function test_telas_de_cadastro_mostram_os_campos_novos(): void
    {
        $eu = $this->actingAs($this->admin);

        $eu->get(route('admin.corretoras.index'))
            ->assertOk()
            ->assertSee('E-mails (Para)')
            ->assertSee('Mesa.Cafe@Corretora.example.com');

        $eu->get(route('admin.clientes.edit', $this->alfa))
            ->assertOk()
            ->assertSee('E-mails em cópia — AA (opcional)')
            ->assertSee('compras@cliente.example.com');

        $eu->get(route('admin.users.edit', $this->admin))
            ->assertOk()
            ->assertSee('Assinatura de e-mail (instruções de AA)')
            ->assertSee('Logistics Assistant');
    }

    /** As listas de e-mail passam pela validação antes de serem salvas. */
    public function test_email_invalido_no_cadastro_e_recusado(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.clientes.edit', $this->alfa))
            ->put(route('admin.clientes.update', $this->alfa), [
                'nome' => 'Alfa',
                'endereco' => 'Madrid – Spain',
                'emails_copia' => 'compras@cliente.example.com; isto-nao-e-email',
            ])
            ->assertSessionHasErrors('emails_copia');

        $this->assertSame('compras@cliente.example.com', $this->alfa->fresh()->emails_copia);
    }

    /** O botão "AA" da lista de fixações usa o grupo inteiro, não a linha. */
    public function test_botao_da_lista_traz_todos_os_contratos_do_mesmo_negocio(): void
    {
        $this->fixar($this->contrato('5961'));
        $this->fixar($this->contrato('5977'));

        $html = $this->actingAs($this->admin)->get(route('ny.fixacoes.index'))->assertOk()->getContent();

        // As duas linhas são do mesmo grupo e do mesmo cliente, então o link
        // de cada uma tem de listar os DOIS contratos (percent-encoded no href).
        $this->assertStringContainsString('✉ AA', $html);
        $this->assertStringContainsString('UT%205961', $html);
        $this->assertStringContainsString('UT%205977', $html);
    }
}
