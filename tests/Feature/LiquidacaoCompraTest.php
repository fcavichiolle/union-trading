<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Compra;
use App\Models\Fornecedor;
use App\Models\Role;
use App\Models\User;
use App\Services\PainelInicial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Liquidação: o armazém quase nunca recebe exatamente o contratado. Enquanto
 * ninguém decide, o sistema avisa da diferença (pode haver café a receber).
 * Ao liquidar, o volume entregue passa a ser o final e os avisos param — sem
 * apagar o contratado, que fica como histórico da quebra/excedente.
 */
class LiquidacaoCompraTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $diretoria;
    private Compra $compra;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $diretoria = Role::create(['slug' => 'diretoria', 'nome' => 'Diretoria']);

        $this->admin = User::create([
            'role_id' => $admin->id, 'name' => 'Admin', 'email' => 'admin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
        $this->diretoria = User::create([
            'role_id' => $diretoria->id, 'name' => 'Dir', 'email' => 'dir@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);

        $fornecedor = Fornecedor::create(['nome' => 'RENATO MARTINS RICCI', 'documento' => '12345678000199']);
        $this->compra = Compra::create([
            'uts' => 'UTS 7412', 'data_compra' => '2026-08-11', 'fornecedor_id' => $fornecedor->id,
            'certificacao' => '4C', 'logistica' => 'POSTO', 'tipo_entrada' => 'ARABICA',
            'volume_contratado' => 250, 'valor_saca' => 1322, 'created_by' => $this->admin->id,
        ]);
    }

    private function entregar(float $sacas, ?string $lote = 'L-1'): void
    {
        $this->compra->entregas()->create([
            'data_entrega' => '2026-08-01', 'armazem' => 'SAAG', 'volume_sacas' => $sacas,
            'numero_lote' => $lote, 'created_by' => $this->admin->id,
        ]);
        $this->compra->refresh();
    }

    /* ---------- Entregou a mais ---------- */

    public function test_liquidar_reconhece_o_volume_entregue_a_mais(): void
    {
        $this->entregar(260); // contratado 250

        $this->assertTrue($this->compra->divergenciaPendente());
        $this->assertEqualsWithDelta(250.0, $this->compra->volumeReconhecido(), 0.01);

        $this->actingAs($this->admin)
            ->patch(route('compras.liquidar', $this->compra))
            ->assertRedirect(route('compras.show', $this->compra));

        $this->compra->refresh();
        $this->assertTrue($this->compra->liquidada());
        $this->assertFalse($this->compra->divergenciaPendente());
        // O sistema passa a reconhecer as 260 entregues…
        $this->assertEqualsWithDelta(260.0, $this->compra->volumeReconhecido(), 0.01);
        // …e o contratado continua gravado como histórico.
        $this->assertEqualsWithDelta(250.0, (float) $this->compra->volume_contratado, 0.01);
    }

    /* ---------- Entregou a menos ---------- */

    public function test_liquidar_encerra_a_compra_que_veio_a_menos(): void
    {
        $this->entregar(240); // contratado 250, e ficou por isso mesmo

        $this->assertTrue(Compra::comSaldoAEntregar()->where('id', $this->compra->id)->exists());

        $this->actingAs($this->admin)->patch(route('compras.liquidar', $this->compra));

        $this->compra->refresh();
        $this->assertTrue($this->compra->liquidada());
        // Deixa de contar como saldo a entregar.
        $this->assertFalse(Compra::comSaldoAEntregar()->where('id', $this->compra->id)->exists());
        $this->assertEqualsWithDelta(240.0, $this->compra->volumeReconhecido(), 0.01);
        // Paga-se pelas 240 que entraram.
        $this->assertEqualsWithDelta(240 * 1322, $this->compra->valorEntregue(), 0.01);
    }

    public function test_liquidada_sai_da_pendencia_do_painel(): void
    {
        $this->entregar(240);
        $this->assertSame(1, (new PainelInicial)->numeros()['compras_com_saldo']);

        $this->actingAs($this->admin)->patch(route('compras.liquidar', $this->compra));

        $this->assertSame(0, (new PainelInicial)->numeros()['compras_com_saldo']);
    }

    /* ---------- Avisos na tela ---------- */

    public function test_tela_da_compra_oferece_liquidar_e_depois_confirma(): void
    {
        $this->entregar(260);

        $this->actingAs($this->admin)->get(route('compras.show', $this->compra))
            ->assertOk()
            ->assertSee('Entraram 10,00 sc a mais que o contratado.')
            ->assertSee('Liquidar compra');

        $this->actingAs($this->admin)->patch(route('compras.liquidar', $this->compra));

        $this->actingAs($this->admin)->get(route('compras.show', $this->compra))
            ->assertOk()
            ->assertSee('Compra liquidada com 260,00 sc.')
            ->assertDontSee('Liquidar compra');
    }

    public function test_lista_troca_o_aviso_de_diferenca_por_liquidada(): void
    {
        $this->entregar(260);

        $this->actingAs($this->admin)->get(route('compras.index'))
            ->assertOk()
            ->assertSee('a mais');

        $this->actingAs($this->admin)->patch(route('compras.liquidar', $this->compra));

        $this->actingAs($this->admin)->get(route('compras.index'))
            ->assertOk()
            ->assertSee('liquidada')
            ->assertDontSee('a mais');
    }

    public function test_filtro_de_divergencia_a_liquidar(): void
    {
        $this->entregar(260); // divergente

        $outra = Compra::create([
            'uts' => 'UTS OK', 'data_compra' => '2026-08-11', 'fornecedor_id' => $this->compra->fornecedor_id,
            'certificacao' => '4C', 'volume_contratado' => 100, 'valor_saca' => 1000,
            'created_by' => $this->admin->id,
        ]);
        $outra->entregas()->create([
            'data_entrega' => '2026-08-01', 'armazem' => 'SAAG', 'volume_sacas' => 100,
            'numero_lote' => 'L-2', 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->get(route('compras.index', ['pendencia' => 'divergente']))
            ->assertOk()
            ->assertSee('UTS 7412')
            ->assertDontSee('UTS OK');

        // Depois de liquidar, sai do filtro. (Confere pelos dados da view:
        // o flash do "liquidada" cita a UTS na própria página.)
        $this->actingAs($this->admin)->patch(route('compras.liquidar', $this->compra));
        $resposta = $this->actingAs($this->admin)
            ->get(route('compras.index', ['pendencia' => 'divergente']))
            ->assertOk();
        $this->assertCount(0, $resposta->viewData('compras'));
    }

    /* ---------- Regras e reabertura ---------- */

    public function test_nao_liquida_compra_sem_nenhuma_entrega(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('compras.liquidar', $this->compra))
            ->assertSessionHasErrors('liquidacao');

        $this->assertFalse($this->compra->fresh()->liquidada());
    }

    public function test_liquidar_duas_vezes_nao_faz_nada(): void
    {
        $this->entregar(260);
        $this->actingAs($this->admin)->patch(route('compras.liquidar', $this->compra));
        $primeira = $this->compra->fresh()->liquidada_em;

        $this->actingAs($this->admin)->patch(route('compras.liquidar', $this->compra))
            ->assertSessionHas('status', 'Esta compra já estava liquidada.');

        $this->assertEquals($primeira, $this->compra->fresh()->liquidada_em);
    }

    public function test_reabrir_devolve_o_aviso_de_diferenca(): void
    {
        $this->entregar(240);
        $this->actingAs($this->admin)->patch(route('compras.liquidar', $this->compra));

        $this->actingAs($this->admin)
            ->patch(route('compras.reabrir', $this->compra))
            ->assertRedirect(route('compras.show', $this->compra));

        $this->compra->refresh();
        $this->assertFalse($this->compra->liquidada());
        $this->assertTrue($this->compra->divergenciaPendente());
        $this->assertSame(1, (new PainelInicial)->numeros()['compras_com_saldo']);
    }

    public function test_liquidacao_e_reabertura_ficam_no_audit_log(): void
    {
        $this->entregar(260);
        $this->actingAs($this->admin)->patch(route('compras.liquidar', $this->compra));
        $this->actingAs($this->admin)->patch(route('compras.reabrir', $this->compra));

        $liquidacao = AuditLog::where('acao', 'compra_liquidada')->first();
        $this->assertNotNull($liquidacao);
        $this->assertStringContainsString('260,00 sc', $liquidacao->descricao);
        $this->assertStringContainsString('contratado 250,00 sc', $liquidacao->descricao);
        $this->assertTrue(AuditLog::where('acao', 'compra_reaberta')->exists());
    }

    public function test_compra_entregue_exatamente_nao_mostra_liquidacao(): void
    {
        $this->entregar(250); // bateu com o contratado

        $this->assertFalse($this->compra->divergenciaPendente());
        $this->assertTrue($this->compra->totalmenteEntregue());

        $this->actingAs($this->admin)->get(route('compras.show', $this->compra))
            ->assertOk()
            ->assertDontSee('Liquidar compra');
    }

    public function test_perfil_sem_permissao_nao_liquida(): void
    {
        $this->entregar(260);

        $this->actingAs($this->diretoria)->patch(route('compras.liquidar', $this->compra))->assertForbidden();
        $this->actingAs($this->diretoria)->patch(route('compras.reabrir', $this->compra))->assertForbidden();
    }

    /* ---------- Valor por entrega ---------- */

    public function test_tela_mostra_o_valor_de_cada_entrega(): void
    {
        $this->entregar(100);
        $this->entregar(160);

        $this->actingAs($this->admin)->get(route('compras.show', $this->compra))
            ->assertOk()
            ->assertSee('Valor da entrega')
            ->assertSee('R$ 132.200,00')  // 100 × 1.322
            ->assertSee('R$ 211.520,00')  // 160 × 1.322
            ->assertSee('R$ 343.720,00'); // total entregue
    }
}
