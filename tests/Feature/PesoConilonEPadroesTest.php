<?php

namespace Tests\Feature;

use App\Models\Classificacao;
use App\Models\Compra;
use App\Models\Entrega;
use App\Models\Fornecedor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Mudanças de agosto/2026 pedidas pela mesa:
 *
 *  - o café é ARÁBICA ou CONILON, e conilon não tem padrão nem bebida;
 *  - compra e entrega aceitam SACAS ou PESO, cada um completando o outro
 *    a 60 kg/saca (mas sem "consertar" divergência informada);
 *  - a entrega guarda o DIA (auditoria), não só o mês;
 *  - padrões novos (Very Good Cup e as três variações Bica) e as faixas
 *    SCS 12 UP / 13 UP.
 */
class PesoConilonEPadroesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Fornecedor $fornecedor;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $this->admin = User::create([
            'role_id' => $role->id, 'name' => 'Admin', 'email' => 'admin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
        $this->fornecedor = Fornecedor::create(['nome' => 'FAZENDA TESTE', 'documento' => '11222333000181']);
    }

    /** Campos do formulário de nova compra, com o mínimo obrigatório. */
    private function dadosDaCompra(array $extra = []): array
    {
        return array_merge([
            'uts' => 'UTS 9000',
            'data_compra' => '2026-08-12',
            'fornecedor_nome' => 'FAZENDA TESTE',
            'certificacao' => 'RFA',
            'tipo_entrada' => 'ARABICA',
            'padrao_final' => 'FINE_CUP',
            'tipo_bebida' => 'DURO',
            'volume_contratado' => 500,
        ], $extra);
    }

    private function compraCrua(array $extra = []): Compra
    {
        return Compra::create(array_merge([
            'uts' => 'UTS 9100', 'data_compra' => '2026-08-12',
            'fornecedor_id' => $this->fornecedor->id, 'certificacao' => 'RFA',
            'tipo_entrada' => 'ARABICA', 'volume_contratado' => 500,
            'created_by' => $this->admin->id,
        ], $extra));
    }

    /* ==============================================================
     * Peso x sacas
     * ============================================================== */

    public function test_compra_com_so_o_peso_calcula_as_sacas(): void
    {
        $this->actingAs($this->admin)
            ->post(route('compras.store'), $this->dadosDaCompra([
                'volume_contratado' => '', 'peso_kg' => 30000,
            ]))
            ->assertSessionHasNoErrors();

        $compra = Compra::firstWhere('uts', 'UTS 9000');
        $this->assertEqualsWithDelta(500.0, (float) $compra->volume_contratado, 0.01);
        $this->assertEqualsWithDelta(30000.0, (float) $compra->peso_kg, 0.01);
    }

    public function test_compra_com_so_as_sacas_calcula_o_peso(): void
    {
        $this->actingAs($this->admin)
            ->post(route('compras.store'), $this->dadosDaCompra(['volume_contratado' => 250]))
            ->assertSessionHasNoErrors();

        $compra = Compra::firstWhere('uts', 'UTS 9000');
        $this->assertEqualsWithDelta(15000.0, (float) $compra->peso_kg, 0.01);
    }

    /**
     * Sacas e peso informados juntos são gravados como vieram, mesmo
     * divergindo: 200 sacas pesando 12.010 kg é a realidade do armazém, e
     * "arredondar" para 12.000 apagaria informação de verdade.
     */
    public function test_sacas_e_peso_divergentes_sao_gravados_como_informados(): void
    {
        $this->actingAs($this->admin)
            ->post(route('compras.store'), $this->dadosDaCompra([
                'volume_contratado' => 200, 'peso_kg' => 12010,
            ]))
            ->assertSessionHasNoErrors();

        $compra = Compra::firstWhere('uts', 'UTS 9000');
        $this->assertEqualsWithDelta(200.0, (float) $compra->volume_contratado, 0.01);
        $this->assertEqualsWithDelta(12010.0, (float) $compra->peso_kg, 0.01);
    }

    public function test_entrega_com_so_o_peso_calcula_as_sacas(): void
    {
        $compra = $this->compraCrua();

        $this->actingAs($this->admin)
            ->post(route('compras.entregas.store', $compra), [
                'data_entrega' => '2026-08-14', 'armazem' => 'SAAG',
                'volume_sacas' => '', 'peso_kg' => 12000,
            ])
            ->assertSessionHasNoErrors();

        $entrega = Entrega::firstWhere('compra_id', $compra->id);
        $this->assertEqualsWithDelta(200.0, (float) $entrega->volume_sacas, 0.01);
        $this->assertEqualsWithDelta(12000.0, (float) $entrega->peso_kg, 0.01);
    }

    public function test_entrega_com_so_as_sacas_calcula_o_peso(): void
    {
        $compra = $this->compraCrua();

        $this->actingAs($this->admin)
            ->post(route('compras.entregas.store', $compra), [
                'data_entrega' => '2026-08-14', 'armazem' => 'SAAG', 'volume_sacas' => 480,
            ])
            ->assertSessionHasNoErrors();

        $entrega = Entrega::firstWhere('compra_id', $compra->id);
        $this->assertEqualsWithDelta(28800.0, (float) $entrega->peso_kg, 0.01);
    }

    public function test_entrega_sem_sacas_e_sem_peso_e_recusada(): void
    {
        $compra = $this->compraCrua();

        $this->actingAs($this->admin)
            ->post(route('compras.entregas.store', $compra), [
                'data_entrega' => '2026-08-14', 'armazem' => 'SAAG',
            ])
            ->assertSessionHasErrors('volume_sacas');

        $this->assertDatabaseCount('entregas', 0);
    }

    /* ==============================================================
     * Data da entrega com dia (auditoria)
     * ============================================================== */

    public function test_entrega_guarda_o_dia_informado(): void
    {
        $compra = $this->compraCrua();

        $this->actingAs($this->admin)
            ->post(route('compras.entregas.store', $compra), [
                'data_entrega' => '2026-08-27', 'armazem' => 'SAAG', 'volume_sacas' => 100,
            ])
            ->assertSessionHasNoErrors();

        // Antes a data era normalizada para o dia 01 do mês, o que apagava
        // exatamente o dado que a auditoria procura.
        $this->assertSame('2026-08-27', Entrega::first()->data_entrega->format('Y-m-d'));
    }

    public function test_estoque_filtra_por_mes_incluindo_o_fim_do_mes(): void
    {
        $compra = $this->compraCrua(['volume_contratado' => 100]);
        $compra->entregas()->create([
            'data_entrega' => '2026-08-31', 'armazem' => 'SAAG', 'volume_sacas' => 100,
            'numero_lote' => 'L-1', 'created_by' => $this->admin->id,
        ]);
        Classificacao::create($this->classificacaoZerada($compra->id, [
            'peneira_1718_pct' => 100, 'peneira_1718_sacas' => 100,
        ]));

        // Filtro "até agosto": o dia 31 tem de entrar. Com o limite antigo
        // (mes_ate . '-01') tudo depois do dia 1º ficava de fora.
        $resposta = $this->actingAs($this->admin)
            ->get(route('relatorio.index', ['mes_de' => '2026-08', 'mes_ate' => '2026-08']))
            ->assertOk();

        $this->assertEqualsWithDelta(100.0, $resposta->viewData('totalGeral'), 0.01);
    }

    /* ==============================================================
     * Arábica x conilon
     * ============================================================== */

    public function test_arabica_exige_padrao_e_bebida(): void
    {
        $this->actingAs($this->admin)
            ->post(route('compras.store'), $this->dadosDaCompra([
                'padrao_final' => '', 'tipo_bebida' => '',
            ]))
            ->assertSessionHasErrors(['padrao_final', 'tipo_bebida']);
    }

    public function test_conilon_nao_exige_padrao_nem_bebida(): void
    {
        $this->actingAs($this->admin)
            ->post(route('compras.store'), $this->dadosDaCompra([
                'tipo_entrada' => 'CONILON', 'padrao_final' => '', 'tipo_bebida' => '',
            ]))
            ->assertSessionHasNoErrors();

        $compra = Compra::firstWhere('uts', 'UTS 9000');
        $this->assertTrue($compra->ehConilon());
        $this->assertNull($compra->padrao_final);
    }

    /** Conilon com padrão enviado na requisição: o servidor descarta. */
    public function test_conilon_grava_padrao_e_bebida_como_nulos_mesmo_se_enviados(): void
    {
        $this->actingAs($this->admin)
            ->post(route('compras.store'), $this->dadosDaCompra([
                'tipo_entrada' => 'CONILON',
                'padrao_final' => 'FINE_CUP',
                'tipo_bebida' => 'DURO',
            ]))
            ->assertSessionHasNoErrors();

        $compra = Compra::firstWhere('uts', 'UTS 9000');
        $this->assertNull($compra->padrao_final);
        $this->assertNull($compra->tipo_bebida);
    }

    /** Virou conilon depois de classificada: o padrão sai dos dois lugares. */
    public function test_mudar_para_conilon_limpa_o_padrao_da_classificacao(): void
    {
        $compra = $this->compraCrua(['padrao_final' => 'FINE_CUP', 'tipo_bebida' => 'DURO']);
        Classificacao::create($this->classificacaoZerada($compra->id, [
            'padrao_final' => 'FINE_CUP', 'tipo_bebida' => 'DURO',
            'peneira_1718_pct' => 100, 'peneira_1718_sacas' => 500,
        ]));

        $this->actingAs($this->admin)
            ->put(route('compras.update', $compra), $this->dadosDaCompra([
                'uts' => $compra->uts, 'tipo_entrada' => 'CONILON',
                'padrao_final' => '', 'tipo_bebida' => '',
            ]))
            ->assertSessionHasNoErrors();

        $compra->refresh()->load('classificacao');
        $this->assertNull($compra->padrao_final);
        $this->assertNull($compra->classificacao->padrao_final);
    }

    /** Classificação de conilon salva sem padrão, só com a distribuição. */
    public function test_classificacao_de_conilon_salva_sem_padrao(): void
    {
        $compra = $this->compraCrua(['tipo_entrada' => 'CONILON', 'volume_contratado' => 300]);

        $payload = [];
        foreach (array_keys(Classificacao::faixas()) as $faixa) {
            $payload[$faixa . '_pct'] = 0;
            $payload[$faixa . '_sacas'] = 0;
        }
        $payload['peneira_1416_pct'] = 100;
        $payload['peneira_1416_sacas'] = 300;

        $this->actingAs($this->admin)
            ->put(route('compras.classificacao.update', $compra), $payload)
            ->assertSessionHasNoErrors();

        $this->assertNull($compra->fresh()->classificacao->padrao_final);
    }

    /* ==============================================================
     * Padrões novos e peneiras novas
     * ============================================================== */

    public static function padroesNovos(): array
    {
        return [
            ['VERY_GOOD_CUP', 'Very Good Cup'],
            ['BICA_FINE_CUP', 'Bica Fine Cup'],
            ['BICA_GOOD_CUP', 'Bica Good Cup'],
            ['BICA_VERY_GOOD_CUP', 'Bica Very Good Cup'],
        ];
    }

    /**
     * Padrão novo entra sem migration de ENUM — era isso que a coluna
     * VARCHAR resolveu. No ENUM antigo o MySQL truncava e o SQLite recusava.
     */
    #[DataProvider('padroesNovos')]
    public function test_padrao_novo_e_aceito_na_compra_e_na_classificacao(string $codigo, string $rotulo): void
    {
        $this->assertArrayHasKey($codigo, Classificacao::padroes());
        $this->assertSame($rotulo, Classificacao::padroes()[$codigo]);

        $this->actingAs($this->admin)
            ->post(route('compras.store'), $this->dadosDaCompra(['padrao_final' => $codigo]))
            ->assertSessionHasNoErrors();

        $compra = Compra::firstWhere('uts', 'UTS 9000');
        $this->assertSame($codigo, $compra->padrao_final);

        $payload = [];
        foreach (array_keys(Classificacao::faixas()) as $faixa) {
            $payload[$faixa . '_pct'] = 0;
            $payload[$faixa . '_sacas'] = 0;
        }
        $payload += ['padrao_final' => $codigo, 'tipo_bebida' => 'DURO'];
        $payload['peneira_12up_pct'] = 100;
        $payload['peneira_12up_sacas'] = 500;

        $this->actingAs($this->admin)
            ->put(route('compras.classificacao.update', $compra), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame($codigo, $compra->fresh()->classificacao->padrao_final);
    }

    public function test_filtros_de_pesquisa_oferecem_os_padroes_novos(): void
    {
        $this->actingAs($this->admin)->get(route('compras.index'))->assertOk()
            ->assertSee('Very Good Cup')->assertSee('Bica Very Good Cup');

        $this->actingAs($this->admin)->get(route('relatorio.index'))->assertOk()
            ->assertSee('Very Good Cup')->assertSee('Bica Fine Cup');
    }

    public function test_peneiras_12up_e_13up_contam_na_soma_e_no_estoque(): void
    {
        $compra = $this->compraCrua(['volume_contratado' => 400]);
        $compra->entregas()->create([
            'data_entrega' => '2026-08-14', 'armazem' => 'SAAG', 'volume_sacas' => 400,
            'numero_lote' => 'L-1', 'created_by' => $this->admin->id,
        ]);

        $payload = ['padrao_final' => 'FINE_CUP', 'tipo_bebida' => 'DURO'];
        foreach (array_keys(Classificacao::faixas()) as $faixa) {
            $payload[$faixa . '_pct'] = 0;
            $payload[$faixa . '_sacas'] = 0;
        }
        // Metade em cada faixa nova: fecha 100% e 400 sacas.
        $payload['peneira_12up_pct'] = 60;
        $payload['peneira_12up_sacas'] = 240;
        $payload['peneira_13up_pct'] = 40;
        $payload['peneira_13up_sacas'] = 160;

        $this->actingAs($this->admin)
            ->put(route('compras.classificacao.update', $compra), $payload)
            ->assertSessionHasNoErrors();

        $classificacao = $compra->fresh()->classificacao;
        $this->assertEqualsWithDelta(400.0, $classificacao->totalSacas(), 0.01);
        $this->assertEqualsWithDelta(100.0, $classificacao->totalPct(), 0.01);
        // Lotes saem da soma de TODAS as faixas.
        $this->assertEqualsWithDelta(round(400 / 283.49, 4), (float) $classificacao->quantidade_lotes, 0.0001);

        // E o Estoque mostra as duas colunas novas com o volume rateado.
        $resposta = $this->actingAs($this->admin)->get(route('relatorio.index'))->assertOk();
        $linha = $resposta->viewData('linhas')->first();

        $this->assertEqualsWithDelta(240.0, (float) $linha->peneira_12up, 0.01);
        $this->assertEqualsWithDelta(160.0, (float) $linha->peneira_13up, 0.01);
        $resposta->assertSee('SCS 12 UP')->assertSee('SCS 13 UP');
    }

    public function test_soma_das_porcentagens_continua_obrigando_100_com_as_faixas_novas(): void
    {
        $compra = $this->compraCrua(['volume_contratado' => 400]);

        $payload = ['padrao_final' => 'FINE_CUP', 'tipo_bebida' => 'DURO'];
        foreach (array_keys(Classificacao::faixas()) as $faixa) {
            $payload[$faixa . '_pct'] = 0;
            $payload[$faixa . '_sacas'] = 0;
        }
        $payload['peneira_12up_pct'] = 60; // falta 40%
        $payload['peneira_12up_sacas'] = 240;

        $this->actingAs($this->admin)
            ->put(route('compras.classificacao.update', $compra), $payload)
            ->assertSessionHasErrors('soma_pct');
    }

    /** @return array<string,mixed> */
    private function classificacaoZerada(int $compraId, array $extra = []): array
    {
        $dados = ['compra_id' => $compraId, 'created_by' => $this->admin->id];

        foreach (array_keys(Classificacao::faixas()) as $faixa) {
            $dados[$faixa . '_pct'] = 0;
            $dados[$faixa . '_sacas'] = 0;
        }

        return array_merge($dados, $extra);
    }
}
