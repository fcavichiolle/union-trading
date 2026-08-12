<?php

namespace Tests\Feature;

use App\Models\Classificacao;
use App\Models\Compra;
use App\Models\Fornecedor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tela de ESTOQUE (antigo "relatório de classificação"): somente leitura,
 * com filtros de intervalo de meses, armazém, situação, padrão, certificado
 * e busca por UTS/fornecedor.
 *
 * Importante: o padrão da tela é "estoque definitivo", ou seja, **só entra
 * a compra que já tem número de lote**. Por isso o helper daqui cria as
 * compras já com lote — uma compra sem lote é o caso de exceção, testado
 * em EstoqueTest.
 */
class RelatorioClassificacaoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $this->admin = User::create([
            'role_id' => $role->id,
            'name' => 'Admin Teste',
            'email' => 'admin@teste.com',
            'password' => Hash::make('senha-teste'),
            'force_password_change' => false,
            'active' => true,
        ]);
    }

    private function novaCompraClassificada(array $compraOverrides = [], array $classificacaoOverrides = []): Compra
    {
        $fornecedor = Fornecedor::create([
            'nome' => $compraOverrides['fornecedor_nome'] ?? 'Fazenda Teste',
            'documento' => $compraOverrides['cnpj'] ?? fake()->unique()->numerify('##.###.###/####-##'),
        ]);

        $compra = Compra::create(array_merge([
            'uts' => 'UTS-' . fake()->unique()->numberBetween(1000, 999999),
            'data_compra' => '2026-01-01', 'fornecedor_id' => $fornecedor->id,
            'certificacao' => 'SEM_CERT',
            'volume_contratado' => 300,
            // Com lote: é assim que a compra conta como estoque definitivo,
            // que é o recorte padrão da tela.
            'numero_lote' => 'L-' . fake()->unique()->numberBetween(1000, 999999),
            'created_by' => $this->admin->id,
        ], array_diff_key($compraOverrides, array_flip(['fornecedor_nome', 'cnpj']))));

        // A entrega é o que faz o café entrar em estoque — com lote, para
        // cair no recorte padrão da tela.
        $compra->entregas()->create([
            'data_entrega' => $compra->data_compra,
            'armazem' => $compraOverrides['armazem'] ?? 'SAAG',
            'volume_sacas' => $compra->volume_contratado,
            'numero_lote' => 'L-' . fake()->unique()->numberBetween(1000, 999999),
            'created_by' => $this->admin->id,
        ]);

        Classificacao::create(array_merge([
            'compra_id' => $compra->id,
            'padrao_final' => 'FINE_CUP', 'tipo_bebida' => 'DURO',
            'peneira_1718_pct' => 50, 'peneira_1718_sacas' => 150,
            'peneira_1416_pct' => 30, 'peneira_1416_sacas' => 100,
            'mercado_interno_pct' => 10, 'mercado_interno_sacas' => 30,
            'grinders_pct' => 10, 'grinders_sacas' => 20,
            'created_by' => $this->admin->id,
        ], $classificacaoOverrides));

        return $compra;
    }

    public function test_tabela_de_certificacao_nao_existe_mais_na_tela(): void
    {
        $this->novaCompraClassificada();

        $resposta = $this->actingAs($this->admin)->get(route('relatorio.index'));

        $resposta->assertOk();
        $resposta->assertViewMissing('linhasCertificacao');
        $resposta->assertDontSee('Distribuição por certificação');
    }

    public function test_filtro_de_intervalo_de_meses(): void
    {
        $this->novaCompraClassificada(['data_compra' => '2026-01-01']);
        $this->novaCompraClassificada(['data_compra' => '2026-06-01']);

        $resposta = $this->actingAs($this->admin)
            ->get(route('relatorio.index', ['mes_de' => '2026-01', 'mes_ate' => '2026-01']));

        $resposta->assertOk();
        // Só a compra de janeiro entra: total de sacas = 300 (única linha, Fine Cup).
        $resposta->assertSee('300,00');
    }

    public function test_filtro_por_certificado(): void
    {
        $this->novaCompraClassificada(['certificacao' => 'SEM_CERT'], ['grinders_sacas' => 20]);
        $this->novaCompraClassificada(['certificacao' => 'RFA'], ['grinders_sacas' => 20]);

        $resposta = $this->actingAs($this->admin)
            ->get(route('relatorio.index', ['certificado' => 'RFA']));

        $resposta->assertOk();
        // Com o filtro, só a compra RFA entra: total = 300 sacas (uma linha).
        $totalGeral = $resposta->viewData('totalGeral');
        $this->assertEqualsWithDelta(300.0, $totalGeral, 0.01);
    }

    public function test_filtro_por_padrao(): void
    {
        $this->novaCompraClassificada([], ['padrao_final' => 'FINE_CUP']);
        $this->novaCompraClassificada([], ['padrao_final' => 'GOOD_CUP']);

        $resposta = $this->actingAs($this->admin)
            ->get(route('relatorio.index', ['padrao' => 'GOOD_CUP']));

        $resposta->assertOk();
        $linhas = $resposta->viewData('linhas');
        $this->assertCount(1, $linhas);
        $this->assertSame('GOOD_CUP', $linhas->first()->padrao_final);
    }

    public function test_filtro_de_busca_por_uts_ou_fornecedor(): void
    {
        $this->novaCompraClassificada(['uts' => 'UTS-ACHAR-1', 'fornecedor_nome' => 'Outra Fazenda']);
        $this->novaCompraClassificada(['uts' => 'UTS-OUTRA-2', 'fornecedor_nome' => 'Fazenda Certa']);

        $resposta = $this->actingAs($this->admin)
            ->get(route('relatorio.index', ['busca' => 'Fazenda Certa']));

        $resposta->assertOk();
        $totalGeral = $resposta->viewData('totalGeral');
        $this->assertEqualsWithDelta(300.0, $totalGeral, 0.01);
    }

    public function test_link_compartilhavel_preserva_os_filtros(): void
    {
        $resposta = $this->actingAs($this->admin)->post(route('relatorio.link'), [
            'mes_de' => '2026-01',
            'mes_ate' => '2026-03',
            'certificado' => 'RFA',
        ]);

        $resposta->assertRedirect();
        $link = session('linkGerado');

        $this->assertStringContainsString('mes_de=2026-01', $link);
        $this->assertStringContainsString('mes_ate=2026-03', $link);
        $this->assertStringContainsString('certificado=RFA', $link);
    }

    /** O armazém é informação de casa: não viaja no link compartilhável. */
    public function test_link_compartilhavel_nao_leva_o_armazem(): void
    {
        $this->actingAs($this->admin)->post(route('relatorio.link'), [
            'mes_de' => '2026-01',
            ])->assertRedirect();

        $this->assertStringNotContainsString('armazem', session('linkGerado'));
    }
}
