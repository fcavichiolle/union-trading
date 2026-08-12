<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Corretora;
use App\Models\Qualidade;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * As posições de bolsa (telas) são CALCULADAS a partir da data — antes eram
 * três anos escritos à mão no código, o que deixava posição vencida na tela
 * (H6, K6, N6 em agosto/2026) e obrigava a editar código todo janeiro.
 *
 * O tempo é congelado nos testes de propósito: sem isso o teste passaria
 * hoje e falharia no ano que vem, que é o problema que estamos resolvendo.
 */
class PosicoesDeBolsaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $this->admin = User::create([
            'role_id' => $role->id, 'name' => 'Admin', 'email' => 'admin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ==============================================================
     * A lista anda com o calendário
     * ============================================================== */

    public function test_em_agosto_de_2026_as_posicoes_de_marco_maio_e_julho_sairam(): void
    {
        Carbon::setTestNow('2026-08-12');

        $abertas = Contrato::mesesFixacaoSantos();

        // Era exatamente a reclamação: H6/K6/N6 já venceram.
        $this->assertArrayNotHasKey('H6', $abertas);
        $this->assertArrayNotHasKey('K6', $abertas);
        $this->assertArrayNotHasKey('N6', $abertas);

        // E o que ainda se negocia continua lá.
        $this->assertArrayHasKey('U6', $abertas);
        $this->assertArrayHasKey('Z6', $abertas);
        $this->assertSame('U6 (Setembro/2026) · NY ICE', $abertas['U6']);
    }

    public function test_no_ano_seguinte_a_lista_se_atualiza_sozinha(): void
    {
        Carbon::setTestNow('2027-01-15');

        $abertas = Contrato::mesesFixacaoSantos();

        // Nada de 2026 sobra…
        foreach (['H6', 'K6', 'N6', 'U6', 'Z6'] as $vencida) {
            $this->assertArrayNotHasKey($vencida, $abertas, "{$vencida} deveria ter saído da lista em 2027");
        }

        // …e o ano novo entrou sem ninguém editar código.
        $this->assertArrayHasKey('H7', $abertas);
        $this->assertArrayHasKey('Z7', $abertas);
        $this->assertSame('H7 (Março/2027) · NY ICE', $abertas['H7']);
    }

    /** Dentro do próprio mês do vencimento a posição ainda é negociada. */
    public function test_a_posicao_do_mes_corrente_continua_disponivel(): void
    {
        Carbon::setTestNow('2026-09-28');

        $this->assertArrayHasKey('U6', Contrato::mesesFixacaoSantos());
    }

    public function test_oferece_tres_anos_a_frente(): void
    {
        Carbon::setTestNow('2026-08-12');

        $anos = collect(array_keys(Contrato::mesesFixacaoVitoria()))
            ->map(fn (string $codigo) => (int) substr($codigo, -4))
            ->unique()->sort()->values()->all();

        $this->assertSame([2026, 2027, 2028, 2029], $anos);
    }

    /** Londres não tem dezembro — os vencimentos vão até novembro. */
    public function test_londres_nao_tem_dezembro(): void
    {
        Carbon::setTestNow('2026-08-12');

        $abertas = Contrato::mesesFixacaoVitoria();

        $this->assertArrayHasKey('Nov_2026', $abertas);
        $this->assertArrayNotHasKey('Dec_2026', $abertas);
        $this->assertArrayNotHasKey('Dec_2027', $abertas);
    }

    /* ==============================================================
     * O histórico não pode quebrar
     * ============================================================== */

    public function test_validacao_ainda_aceita_posicao_vencida(): void
    {
        Carbon::setTestNow('2026-08-12');

        // A lista dos formulários não tem H6…
        $this->assertArrayNotHasKey('H6', Contrato::mesesFixacao());
        // …mas a janela de validação tem, senão contrato antigo não salva.
        $this->assertArrayHasKey('H6', Contrato::mesesFixacaoTodas());
        $this->assertArrayHasKey('Mar_2025', Contrato::mesesFixacaoTodas());
    }

    /** O caso que quebraria de verdade: editar um contrato fixado em H6. */
    public function test_editar_contrato_fixado_em_posicao_vencida_funciona(): void
    {
        Carbon::setTestNow('2026-08-12');

        $contrato = $this->contrato(['mes_fixacao' => 'H6']);

        $this->actingAs($this->admin)
            ->put(route('contratos.update', $contrato), $this->camposDoContrato([
                'mes_fixacao' => 'H6', 'quantidade_kg' => 120000,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('contratos.show', $contrato));

        $this->assertSame('H6', $contrato->fresh()->mes_fixacao);
    }

    /** E o dropdown mostra essa posição, marcada — senão salvar a apagaria. */
    public function test_formulario_de_edicao_mostra_a_posicao_vencida_do_contrato(): void
    {
        Carbon::setTestNow('2026-08-12');

        $contrato = $this->contrato(['mes_fixacao' => 'H6']);

        // O rótulo vai para o JS via @json, que escapa acento e "·" como
        // \uXXXX — por isso a asserção usa só os trechos ASCII.
        $this->actingAs($this->admin)->get(route('contratos.edit', $contrato))
            ->assertOk()
            ->assertSee('H6 (Mar', false)
            ->assertSee('vencida', false);
    }

    public function test_formulario_de_novo_contrato_nao_oferece_posicao_vencida(): void
    {
        Carbon::setTestNow('2026-08-12');

        $resposta = $this->actingAs($this->admin)->get(route('contratos.create'))->assertOk();

        $resposta->assertDontSee('H6 (Mar', false);
        $resposta->assertSee('Z6 (Dezembro\/2026)', false);
    }

    /* ==============================================================
     * Tela NY
     * ============================================================== */

    /** Contrato esperando fixação numa posição vencida: dá para fixar nela. */
    public function test_tela_ny_permite_fixar_em_posicao_vencida_de_contrato_em_aberto(): void
    {
        Carbon::setTestNow('2026-08-12');

        Corretora::create(['nome' => 'Corretora Aurora', 'tipo' => 'NOSSA']);
        $contrato = $this->contrato(['mes_fixacao' => 'K6']);

        $this->actingAs($this->admin)->get(route('ny.index'))->assertOk()
            ->assertSee('K6 (Maio', false)
            ->assertSee('vencida', false);

        $this->actingAs($this->admin)->post(route('ny.fixacoes.store'), [
            'contratos' => [$contrato->id],
            'corretora' => 'Corretora Aurora',
            'tela' => 'K6',
            // Modo de um contrato exige os lotes (fixação parcial é permitida).
            'lotes' => $contrato->lotes,
            'level' => '335.00',
            'diferenciais' => [$contrato->id => '-16.00'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('K6', $contrato->fixacoes()->first()->tela);
    }

    public function test_tela_de_outra_bolsa_continua_recusada(): void
    {
        Carbon::setTestNow('2026-08-12');

        Corretora::create(['nome' => 'Corretora Aurora', 'tipo' => 'NOSSA']);
        $contrato = $this->contrato(['porto' => 'SANTOS', 'mes_fixacao' => 'Z6']);

        // Sep_2026 é de Londres: recusa mesmo estando em aberto.
        $this->actingAs($this->admin)->post(route('ny.fixacoes.store'), [
            'contratos' => [$contrato->id],
            'corretora' => 'Corretora Aurora',
            'tela' => 'Sep_2026',
            'level' => '3800',
            'diferenciais' => [$contrato->id => '-65'],
        ])->assertSessionHasErrors('tela');
    }

    public function test_rotulo_de_tela_desconhecida_devolve_o_proprio_codigo(): void
    {
        Carbon::setTestNow('2026-08-12');

        $this->assertSame('Z6 (Dezembro/2026) · NY ICE', Contrato::rotuloDaTela('Z6'));
        $this->assertSame('XYZ', Contrato::rotuloDaTela('XYZ'));
        $this->assertNull(Contrato::rotuloDaTela(null));
    }

    /* ---------- helpers ---------- */

    /** @return array<string,mixed> */
    private function camposDoContrato(array $extra = []): array
    {
        return array_merge([
            'numero_ut' => '6100',
            'data_contrato' => '2026-08-12',
            'cliente_id' => Cliente::firstOrCreate(['nome' => 'NORTHBROOK COFFEE TRADING LLC'], ['endereco' => 'STAMFORD, CT'])->id,
            'qualidade_id' => Qualidade::firstOrCreate(['descricao' => 'BRAZIL SANTOS 17/18 FC'])->id,
            'tipo_cafe' => 'ARABICA',
            'certificado' => 'SEM_CERT',
            'quantidade_kg' => 102060,
            'tipo_container' => '20',
            'embalagem' => 'Bulk Liner',
            'incoterms' => 'FOB',
            'porto' => 'SANTOS',
            'diferencial' => '-16.00',
        ], $extra);
    }

    private function contrato(array $extra = []): Contrato
    {
        $campos = $this->camposDoContrato($extra);
        $cliente = Cliente::find($campos['cliente_id']);
        $qualidade = Qualidade::find($campos['qualidade_id']);

        return Contrato::create($campos + [
            'cliente_nome' => $cliente->nome,
            'cliente_endereco' => $cliente->endereco,
            'qualidade_descricao' => $qualidade->descricao,
            'created_by' => $this->admin->id,
        ]);
    }
}
