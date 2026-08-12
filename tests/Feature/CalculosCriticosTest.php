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
 * Cálculos críticos recalculados SEMPRE no servidor (nunca aceitos do
 * formulário): quantidade de lotes (soma das sacas ÷ 283,49) e valor
 * total (valor da saca × volume da compra).
 */
class CalculosCriticosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Compra $compra;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $this->user = User::create([
            'role_id' => $role->id,
            'name' => 'Admin Teste',
            'email' => 'admin@teste.com',
            'password' => Hash::make('senha-teste'),
            'force_password_change' => false,
            'active' => true,
        ]);
        $fornecedor = Fornecedor::create(['nome' => 'Fazenda Teste', 'documento' => '12345678000199']);
        $this->compra = Compra::create([
            'uts' => 'UTS-2026-TESTE',
            'data_compra' => '2026-01-01', 'fornecedor_id' => $fornecedor->id,
            'certificacao' => 'SEM_CERT',
            'volume_contratado' => 300,
            'created_by' => $this->user->id,
        ]);
    }

    private function novaClassificacao(array $overrides = []): Classificacao
    {
        return Classificacao::create(array_merge([
            'compra_id' => $this->compra->id,
            'padrao_final' => 'FINE_CUP', 'tipo_bebida' => 'DURO',
            'peneira_1718_pct' => 50, 'peneira_1718_sacas' => 150,
            'peneira_1416_pct' => 30, 'peneira_1416_sacas' => 100,
            'mercado_interno_pct' => 10, 'mercado_interno_sacas' => 30,
            'grinders_pct' => 10, 'grinders_sacas' => 20,
            'created_by' => $this->user->id,
        ], $overrides));
    }

    public function test_quantidade_de_lotes_e_a_soma_das_sacas_dividida_por_283_49(): void
    {
        $classificacao = $this->novaClassificacao(); // soma das sacas = 300

        // 300 / 283.49 = 1.0582383... -> arredondado em 4 casas no model = 1.0582
        $this->assertEqualsWithDelta(round(300 / Classificacao::SACAS_POR_LOTE, 4), (float) $classificacao->quantidade_lotes, 0.00001);
        $this->assertEqualsWithDelta(1.0582, (float) $classificacao->quantidade_lotes, 0.00001);
    }

    public function test_quantidade_de_lotes_ignora_valor_enviado_e_recalcula_no_servidor(): void
    {
        $classificacao = $this->novaClassificacao();

        // Tenta forçar um valor absurdo direto no atributo e salvar.
        $classificacao->quantidade_lotes = 999999;
        $classificacao->save();
        $classificacao->refresh();

        // O evento saving recalcula: o valor forjado é descartado.
        $this->assertEqualsWithDelta(round(300 / Classificacao::SACAS_POR_LOTE, 4), (float) $classificacao->quantidade_lotes, 0.00001);
    }

    public function test_lotes_acompanham_a_soma_das_quatro_faixas_de_sacas(): void
    {
        // 200 + 0 + 0 + 0 = 200 sacas
        $classificacao = $this->novaClassificacao([
            'peneira_1718_sacas' => 200,
            'peneira_1416_sacas' => 0,
            'mercado_interno_sacas' => 0,
            'grinders_sacas' => 0,
        ]);

        $this->assertEqualsWithDelta(round(200 / Classificacao::SACAS_POR_LOTE, 4), (float) $classificacao->quantidade_lotes, 0.00001);
    }

    public function test_moka_soma_no_calculo_de_lotes(): void
    {
        // Transfere parte do grinders para moka, mantendo a soma de sacas em 300.
        $classificacao = $this->novaClassificacao([
            'grinders_pct' => 5, 'grinders_sacas' => 10,
            'moka_pct' => 5, 'moka_sacas' => 10,
        ]);

        $this->assertEqualsWithDelta(round(300 / Classificacao::SACAS_POR_LOTE, 4), (float) $classificacao->quantidade_lotes, 0.00001);
    }

    public function test_valor_contratado_e_o_preco_vezes_o_volume_contratado(): void
    {
        $this->compra->update(['valor_saca' => 1000]);

        // 1000 × 300 contratadas
        $this->assertEqualsWithDelta(300000.00, $this->compra->valorContratado(), 0.001);
    }

    /**
     * O valor efetivo segue o que REALMENTE entrou no armazém — é por ele
     * que se paga, e ele muda conforme o funcionário 3 confere as entregas.
     */
    public function test_valor_efetivo_acompanha_o_volume_entregue(): void
    {
        $this->compra->update(['valor_saca' => 1000]);

        // Nada entregue ainda: não há o que pagar.
        $this->assertEqualsWithDelta(0.0, $this->compra->valorEntregue(), 0.001);

        $this->compra->entregas()->create([
            'data_entrega' => '2026-01-01', 'armazem_id' => $this->armazem('SAAG'), 'volume_sacas' => 280,
            'numero_lote' => 'L-1', 'created_by' => $this->user->id,
        ]);

        // 1000 × 280 entregues (e não as 300 contratadas).
        $this->assertEqualsWithDelta(280000.00, $this->compra->refresh()->valorEntregue(), 0.001);
        $this->assertEqualsWithDelta(300000.00, $this->compra->valorContratado(), 0.001);
    }

    public function test_valores_nao_sao_gravados_no_banco_sao_sempre_calculados(): void
    {
        $this->compra->update(['valor_saca' => 1000]);

        // Não existe coluna de total: qualquer tentativa de forjar o número
        // morre no model, porque ele é derivado de preço × volume.
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('compras', 'valor_total'),
            'O total não deve ser persistido — é sempre recalculado.'
        );
        $this->assertEqualsWithDelta(300000.00, $this->compra->valorContratado(), 0.001);
    }
}
