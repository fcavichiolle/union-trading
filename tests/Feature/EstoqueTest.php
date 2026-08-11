<?php

namespace Tests\Feature;

use App\Models\Classificacao;
use App\Models\Compra;
use App\Models\Fornecedor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Regra do estoque: só entra definitivamente quem tem o número do lote
 * informado pelo armazém. O que fica de fora precisa aparecer como aviso —
 * nunca sumir em silêncio.
 */
class EstoqueTest extends TestCase
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
        $this->fornecedor = Fornecedor::create(['nome' => 'Fazenda Teste', 'cnpj' => '12345678000199']);
    }

    private function compra(string $uts, string $armazem, ?string $lote, float $sacas = 300): Compra
    {
        return Compra::create([
            'uts' => $uts, 'mes_ano' => '2026-01-01', 'fornecedor_id' => $this->fornecedor->id,
            'armazem' => $armazem, 'certificacao' => 'SEM_CERT', 'tipo_entrada' => 'BICA',
            'volume_sacas' => $sacas, 'numero_lote' => $lote, 'created_by' => $this->admin->id,
        ]);
    }

    private function classificar(Compra $compra, string $padrao = 'FINE_CUP'): Classificacao
    {
        $sacas = (float) $compra->volume_sacas;

        return Classificacao::create([
            'compra_id' => $compra->id, 'padrao_final' => $padrao, 'tipo_bebida' => 'DURO',
            'peneira_1718_pct' => 100, 'peneira_1718_sacas' => $sacas,
            'peneira_1416_pct' => 0, 'peneira_1416_sacas' => 0,
            'mercado_interno_pct' => 0, 'mercado_interno_sacas' => 0,
            'grinders_pct' => 0, 'grinders_sacas' => 0,
            'moka_pct' => 0, 'moka_sacas' => 0,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_por_padrao_so_conta_compra_com_numero_de_lote(): void
    {
        $this->classificar($this->compra('COM-LOTE', 'SAAG', 'L-1', 300));
        $this->classificar($this->compra('SEM-LOTE', 'SAAG', null, 500));

        $resposta = $this->actingAs($this->admin)->get(route('relatorio.index'))->assertOk();

        // Só as 300 sacas com lote entram no estoque definitivo.
        $this->assertEqualsWithDelta(300.0, $resposta->viewData('totalGeral'), 0.01);
    }

    public function test_situacao_aguardando_mostra_so_o_que_falta_lote(): void
    {
        $this->classificar($this->compra('COM-LOTE', 'SAAG', 'L-1', 300));
        $this->classificar($this->compra('SEM-LOTE', 'SAAG', null, 500));

        $resposta = $this->actingAs($this->admin)
            ->get(route('relatorio.index', ['situacao' => 'aguardando']))
            ->assertOk();

        $this->assertEqualsWithDelta(500.0, $resposta->viewData('totalGeral'), 0.01);
    }

    public function test_situacao_todos_soma_tudo(): void
    {
        $this->classificar($this->compra('COM-LOTE', 'SAAG', 'L-1', 300));
        $this->classificar($this->compra('SEM-LOTE', 'SAAG', null, 500));

        $resposta = $this->actingAs($this->admin)
            ->get(route('relatorio.index', ['situacao' => 'todos']))
            ->assertOk();

        $this->assertEqualsWithDelta(800.0, $resposta->viewData('totalGeral'), 0.01);
    }

    public function test_volume_aguardando_lote_aparece_como_aviso(): void
    {
        $this->classificar($this->compra('SEM-LOTE-1', 'SAAG', null, 500));
        $this->classificar($this->compra('SEM-LOTE-2', 'QUALITE', null, 300));

        $resposta = $this->actingAs($this->admin)->get(route('relatorio.index'))->assertOk();

        $pendentes = $resposta->viewData('pendentes');
        $this->assertEqualsWithDelta(800.0, $pendentes['aguardando_sacas'], 0.01);
        $this->assertSame(2, $pendentes['aguardando_compras']);

        // E o usuário lê isso na tela, com atalho para resolver.
        $resposta->assertSee('Fora do estoque:')
            ->assertSee('800,00 sc')
            ->assertSee('aguardando o nº do lote')
            ->assertSee(route('compras.index', ['pendencia' => 'sem_lote']), false);
    }

    public function test_estoque_com_lote_mas_sem_classificacao_e_avisado(): void
    {
        $this->classificar($this->compra('CLASSIFICADA', 'SAAG', 'L-1', 300));
        $this->compra('SEM-CLASSIFICAR', 'SAAG', 'L-2', 450); // com lote, sem classificação

        $resposta = $this->actingAs($this->admin)->get(route('relatorio.index'))->assertOk();

        $pendentes = $resposta->viewData('pendentes');
        $this->assertEqualsWithDelta(450.0, $pendentes['sem_classificacao_sacas'], 0.01);
        $this->assertSame(1, $pendentes['sem_classificacao_compras']);

        // A tabela mostra só as 300 classificadas — mas o aviso denuncia o resto.
        $this->assertEqualsWithDelta(300.0, $resposta->viewData('totalGeral'), 0.01);
        $resposta->assertSee('Em estoque, sem classificação:')
            ->assertSee('450,00 sc');
    }

    public function test_sem_pendencia_nenhuma_a_tela_nao_mostra_avisos(): void
    {
        $this->classificar($this->compra('OK', 'SAAG', 'L-1', 300));

        $this->actingAs($this->admin)->get(route('relatorio.index'))
            ->assertOk()
            ->assertDontSee('Fora do estoque:')
            ->assertDontSee('Em estoque, sem classificação:');
    }

    public function test_agrupa_por_armazem_com_coluna_e_subtotal(): void
    {
        $this->classificar($this->compra('SAAG-1', 'SAAG', 'L-1', 300), 'FINE_CUP');
        $this->classificar($this->compra('SAAG-2', 'SAAG', 'L-2', 200), 'GOOD_CUP');
        $this->classificar($this->compra('QUAL-1', 'QUALITE', 'L-3', 400), 'FINE_CUP');

        $resposta = $this->actingAs($this->admin)->get(route('relatorio.index'))->assertOk();

        $linhas = $resposta->viewData('linhas');
        // 3 linhas: SAAG/Fine, SAAG/Good, QUALITÉ/Fine.
        $this->assertCount(3, $linhas);
        $this->assertSame(['QUALITE', 'SAAG', 'SAAG'], $linhas->pluck('armazem')->all());

        $resposta->assertSee('Armazém')
            ->assertSee('QUALITÉ')
            // SAAG tem 2 padrões => ganha linha de subtotal (300 + 200).
            ->assertSee('Subtotal SAAG')
            ->assertSee('500,00');
    }

    public function test_filtro_por_armazem(): void
    {
        $this->classificar($this->compra('SAAG-1', 'SAAG', 'L-1', 300));
        $this->classificar($this->compra('QUAL-1', 'QUALITE', 'L-2', 400));

        $resposta = $this->actingAs($this->admin)
            ->get(route('relatorio.index', ['armazem' => 'QUALITE']))
            ->assertOk();

        $this->assertEqualsWithDelta(400.0, $resposta->viewData('totalGeral'), 0.01);
        $this->assertSame(['QUALITE'], $resposta->viewData('linhas')->pluck('armazem')->unique()->all());
    }

    public function test_aviso_de_pendencia_respeita_o_filtro_de_armazem(): void
    {
        $this->compra('SAAG-SEM-LOTE', 'SAAG', null, 500);
        $this->compra('QUAL-SEM-LOTE', 'QUALITE', null, 300);

        $resposta = $this->actingAs($this->admin)
            ->get(route('relatorio.index', ['armazem' => 'SAAG']))
            ->assertOk();

        $this->assertEqualsWithDelta(500.0, $resposta->viewData('pendentes')['aguardando_sacas'], 0.01);
    }

    /* ---------- Link público: não expõe armazém ---------- */

    public function test_link_publico_nao_quebra_por_armazem(): void
    {
        $this->classificar($this->compra('SAAG-1', 'SAAG', 'L-1', 300));
        $this->classificar($this->compra('QUAL-1', 'QUALITE', 'L-2', 400));

        $url = URL::temporarySignedRoute('relatorio.publico', now()->addDays(7), []);

        $resposta = $this->get($url)->assertOk();

        // Sem coluna nem nome de armazém na página pública.
        $resposta->assertDontSee('Armazém')
            ->assertDontSee('QUALITÉ')
            ->assertDontSee('SAAG');

        // E os totais são do estoque inteiro (700), não de um armazém só.
        $this->assertEqualsWithDelta(700.0, $resposta->viewData('totalGeral'), 0.01);
        $this->assertFalse($resposta->viewData('comArmazem'));
    }

    public function test_link_publico_ignora_armazem_forcado_na_url(): void
    {
        $this->classificar($this->compra('SAAG-1', 'SAAG', 'L-1', 300));
        $this->classificar($this->compra('QUAL-1', 'QUALITE', 'L-2', 400));

        // Assina COM o parâmetro para a assinatura continuar válida — mesmo
        // assim o controller descarta o recorte por armazém.
        $url = URL::temporarySignedRoute('relatorio.publico', now()->addDays(7), ['armazem' => 'SAAG']);

        $resposta = $this->get($url)->assertOk();

        $this->assertEqualsWithDelta(700.0, $resposta->viewData('totalGeral'), 0.01);
        $this->assertSame('', $resposta->viewData('filtros')['armazem']);
    }

    public function test_link_publico_explica_qual_recorte_esta_mostrando(): void
    {
        $this->classificar($this->compra('SAAG-1', 'SAAG', 'L-1', 300));

        $url = URL::temporarySignedRoute('relatorio.publico', now()->addDays(7), []);

        $this->get($url)->assertOk()
            ->assertSee('Considera apenas o café com entrada confirmada em armazém.');
    }
}
