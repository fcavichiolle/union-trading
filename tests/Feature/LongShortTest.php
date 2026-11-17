<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Contrato;
use App\Models\Embarque;
use App\Models\Entrega;
use App\Models\Fornecedor;
use App\Models\FotoEstoque;
use App\Models\Qualidade;
use App\Models\Role;
use App\Models\User;
use App\Services\LongShort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Posição Long & Short — a conta da aba POSIÇAO_LONG SHORT da planilha:
 *
 *   foto do estoque + entregas depois da foto − baixas depois da foto
 *   + compras a receber − vendas em aberto.
 *
 * O que estes testes guardam: a foto tem DATA, e o que é anterior a ela não
 * entra duas vezes (já está na foto); a bica vira padrão por rendimento.
 */
class LongShortTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Cliente $cliente;
    private Qualidade $qualidade;
    private Fornecedor $fornecedor;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-17 10:00:00');

        $role = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $this->admin = User::create([
            'role_id' => $role->id, 'name' => 'Admin', 'email' => 'admin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
        $this->cliente = Cliente::create(['nome' => 'CLIENTE TESTE', 'endereco' => 'Madrid']);
        $this->qualidade = Qualidade::create(['descricao' => 'NY 3/4 SCREEN 14/16 GOOD CUP']);
        $this->fornecedor = Fornecedor::create(['nome' => 'JOAO EXEMPLO', 'documento' => '12345678000199']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<string, float> $sacasPorBalde */
    private function foto(string $data, array $sacasPorBalde): FotoEstoque
    {
        $foto = FotoEstoque::create(['data_foto' => $data, 'origem' => 'teste']);
        foreach ($sacasPorBalde as $balde => $sacas) {
            $foto->lotes()->create(['lote' => 'L-' . $balde, 'balde' => $balde, 'kg' => $sacas * 60, 'sacas' => $sacas]);
        }

        return $foto;
    }

    private function compra(string $uts, array $overrides = []): Compra
    {
        return Compra::create(array_merge([
            'uts' => $uts, 'data_compra' => '2026-11-05', 'fornecedor_id' => $this->fornecedor->id,
            'certificacao' => 'SEM_CERT', 'tipo_entrada' => 'ARABICA', 'padrao_final' => 'BICA_GOOD_CUP',
            'volume_contratado' => 1000, 'valor_saca' => 1400, 'created_by' => $this->admin->id,
        ], $overrides));
    }

    private function entrega(Compra $compra, string $data, float $sacas): Entrega
    {
        return $compra->entregas()->create([
            'data_entrega' => $data, 'armazem_id' => $this->armazem('SAAG'), 'volume_sacas' => $sacas,
        ]);
    }

    /** Contrato de 600 sc (36.000 kg), 14/16 GC, embarque em dezembro. */
    private function venda(string $ut, array $embarque = [], string $standard = '1416_GC'): Embarque
    {
        $contrato = Contrato::create([
            'numero_ut' => $ut, 'data_contrato' => '2026-08-05',
            'cliente_id' => $this->cliente->id, 'cliente_nome' => $this->cliente->nome, 'cliente_endereco' => $this->cliente->endereco,
            'qualidade_id' => $this->qualidade->id, 'qualidade_descricao' => $this->qualidade->descricao,
            'standard' => $standard, 'tipo_cafe' => 'ARABICA', 'certificado' => 'RFA',
            'quantidade_kg' => 36000, 'tipo_container' => '20', 'embalagem' => 'Bulk Liner',
            'incoterms' => 'FOB', 'porto' => 'SANTOS', 'embarque_mes' => '2026-12-01', 'created_by' => $this->admin->id,
        ]);

        $e = new Embarque(array_merge(['mes_faturamento' => '2026-12-01', 'quantidade_kg' => 36000, 'status' => 'BOOKING'], $embarque));
        $e->contrato()->associate($contrato);
        $e->save();

        return $e;
    }

    private function posicao(): array
    {
        return app(LongShort::class)->calcular();
    }

    /** Lote já preparado conta inteiro no padrão dele; a bica, pelo rendimento. */
    public function test_foto_soma_o_medido_e_distribui_a_bica_pelo_rendimento(): void
    {
        $this->foto('2026-11-03', ['1416_GC' => 1000, 'BICA_GC' => 2000, 'REAPROV' => 100]);

        $p = $this->posicao();

        // Bica GC: 30% 17/18, 50% 14/16, 15% grinders, 5% mercado interno.
        // Reaproveitamento: 10% 17/18, 60% 14/16, 30% grinders.
        $this->assertEqualsWithDelta(1000, $p['linhas']['1416_GC']['medido'], 0.01);
        $this->assertEqualsWithDelta(1060, $p['linhas']['1416_GC']['estimado'], 0.01); // 1000 + 60
        $this->assertEqualsWithDelta(610, $p['linhas']['1718_GC']['estoque_foto'], 0.01); // 600 + 10
        $this->assertEqualsWithDelta(330, $p['linhas']['GRINDERS_12UP']['estoque_foto'], 0.01); // 300 + 30
        $this->assertEqualsWithDelta(100, $p['interno']['estoque_foto'], 0.01);
        // Nada se perde: padrões + mercado interno = o total da foto.
        $this->assertEqualsWithDelta(3100, $p['totais']['estoque_foto'] + $p['interno']['estoque_foto'], 0.01);
    }

    /** O coração da regra: o que é anterior à foto já está nela. */
    public function test_so_a_entrega_a_partir_da_data_da_foto_soma_ao_estoque(): void
    {
        $this->foto('2026-11-03', ['1416_GC' => 1000]);
        $compra = $this->compra('UTS-7400', ['pct_1718' => 20, 'pct_cata' => 30]);
        $this->entrega($compra, '2026-10-30', 400); // já está na foto
        $this->entrega($compra, '2026-11-03', 100); // o dia da foto conta como depois
        $this->entrega($compra, '2026-11-10', 200);

        $p = $this->posicao();

        // 300 sc depois da foto: 20% 17/18, 50% 14/16, cata 30% → 85% grinders.
        $this->assertEqualsWithDelta(60, $p['linhas']['1718_GC']['entradas'], 0.01);
        $this->assertEqualsWithDelta(150, $p['linhas']['1416_GC']['entradas'], 0.01);
        $this->assertEqualsWithDelta(76.5, $p['linhas']['GRINDERS_12UP']['entradas'], 0.01);
        $this->assertEqualsWithDelta(13.5, $p['interno']['entradas'], 0.01);

        // E o que falta chegar (1000 − 700) entra como "a receber".
        $this->assertEqualsWithDelta(150, $p['linhas']['1416_GC']['a_receber'], 0.01);
        $this->assertEqualsWithDelta(1300, $p['linhas']['1416_GC']['disponivel'], 0.01); // 1000 + 150 + 150
    }

    public function test_baixa_depois_da_foto_tira_do_estoque_e_a_anterior_nao(): void
    {
        $this->foto('2026-11-03', ['1416_GC' => 5000]);
        $this->venda('5973', ['status' => 'FATURADO', 'baixado_em' => '2026-10-28']); // já fora da foto
        $this->venda('5974', ['status' => 'FATURADO', 'baixado_em' => '2026-11-05']);
        $this->venda('5975', ['status' => 'FATURADO']); // faturado sem baixa: ainda é venda em aberto

        $linha = $this->posicao()['linhas']['1416_GC'];

        $this->assertEqualsWithDelta(600, $linha['baixas'], 0.01);
        $this->assertEqualsWithDelta(4400, $linha['estoque_hoje'], 0.01);
        $this->assertEqualsWithDelta(600, $linha['vendas'], 0.01);
        $this->assertEqualsWithDelta(3800, $linha['posicao'], 0.01);
    }

    /** A grade: o que sobra do disponível depois das vendas de cada mês. */
    public function test_posicao_acumula_mes_a_mes_e_fica_short_quando_falta_cafe(): void
    {
        $this->foto('2026-11-03', ['1416_GC' => 1000]);
        $this->venda('5973', ['mes_faturamento' => '2026-12-01']);
        $this->venda('5974', ['mes_faturamento' => '2027-01-01']);

        $p = $this->posicao();
        $porMes = $p['linhas']['1416_GC']['posicao_por_mes'];

        $this->assertEqualsWithDelta(1000, $porMes['2026-11'], 0.01);
        $this->assertEqualsWithDelta(400, $porMes['2026-12'], 0.01);
        $this->assertEqualsWithDelta(-200, $porMes['2027-01'], 0.01);
        $this->assertEqualsWithDelta(-200, $porMes['2027-10'], 0.01);
        $this->assertEqualsWithDelta(-200, $p['linhas']['1416_GC']['posicao'], 0.01);
        $this->assertEqualsWithDelta(-200, $p['totais']['posicao'], 0.01);
    }

    /** Compra sem % informado não some: entra pelo rendimento padrão, com aviso. */
    public function test_compra_sem_rendimento_usa_o_padrao_da_familia_e_avisa(): void
    {
        $this->compra('UTS-7401', ['padrao_final' => 'BICA_FINE_CUP']);

        $p = $this->posicao();

        // Fine Cup: 35% de 17/18 e 13% de cata → 52% de 14/16 FC.
        $this->assertEqualsWithDelta(350, $p['linhas']['1718_FC']['a_receber'], 0.01);
        $this->assertEqualsWithDelta(520, $p['linhas']['1416_FC']['a_receber'], 0.01);
        $this->assertStringContainsString('UTS-7401', implode(' ', $p['avisos']));
    }

    public function test_compra_liquidada_e_conilon_nao_entram_como_a_receber(): void
    {
        $this->compra('UTS-7402', ['pct_1718' => 20, 'pct_cata' => 30, 'liquidada_em' => now(), 'liquidada_por' => $this->admin->id]);
        $this->compra('UTS-7403', ['tipo_entrada' => 'CONILON', 'padrao_final' => null]);

        $p = $this->posicao();

        $this->assertEqualsWithDelta(0, $p['totais']['a_receber'], 0.01);
        $this->assertStringContainsString('conilon', implode(' ', $p['avisos']));
    }

    /** O de/para do padrão do Digisul: o desconhecido fica sem balde, não é chutado. */
    public function test_de_para_do_padrao_do_digisul(): void
    {
        $this->assertSame('1416_FC', FotoEstoque::baldeDoPadrao('16 UP'));
        $this->assertSame('1718_GC', FotoEstoque::baldeDoPadrao('PEN19 GC'));
        $this->assertSame('MERCADO_INTERNO', FotoEstoque::baldeDoPadrao('BLACK BEANS'));
        $this->assertSame('BICA_GC', FotoEstoque::baldeDoPadrao(' bica  good cup '));
        $this->assertSame('REAPROV', FotoEstoque::baldeDoPadrao('REAPROVEITAMENTO'));
        $this->assertNull(FotoEstoque::baldeDoPadrao('PADRAO NOVO'));
    }

    public function test_tela_mostra_a_posicao_e_esta_no_menu(): void
    {
        $this->foto('2026-11-03', ['1416_GC' => 1000]);
        $this->venda('5973');
        $this->venda('5974');

        $this->actingAs($this->admin)->get(route('posicao.index'))
            ->assertOk()
            ->assertSee('Posição mês a mês')
            ->assertSee('03/11/2026')
            ->assertSee('short — falta café');

        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee(route('posicao.index'));
    }

    public function test_sem_foto_a_tela_abre_e_avisa(): void
    {
        $this->actingAs($this->admin)->get(route('posicao.index'))
            ->assertOk()
            ->assertSee('Ainda não há foto do estoque');
    }

    /** O % de 17/18 e de cata são lançados na própria compra. */
    public function test_rendimento_e_gravado_pelo_formulario_da_compra(): void
    {
        $this->actingAs($this->admin)->post(route('compras.store'), [
            'uts' => 'UTS-7500', 'data_compra' => '2026-11-10', 'fornecedor_nome' => 'JOAO EXEMPLO',
            'certificacao' => 'SEM_CERT', 'tipo_entrada' => 'ARABICA', 'padrao_final' => 'BICA_GOOD_CUP', 'tipo_bebida' => 'DURO',
            'volume_contratado' => 500, 'pct_1718' => 25, 'pct_cata' => 35,
        ])->assertSessionHasNoErrors();

        $compra = Compra::where('uts', 'UTS-7500')->firstOrFail();
        $this->assertEqualsWithDelta(25, (float) $compra->pct_1718, 0.01);
        $this->assertEqualsWithDelta(0.40, $compra->rendimentoPrevisto()['baldes']['1416_GC'], 0.001);
    }
}
