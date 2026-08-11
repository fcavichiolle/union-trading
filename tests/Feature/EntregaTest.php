<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Entrega;
use App\Models\Fornecedor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Entregas: a mesma UTS entra no armazém em partes, em meses e armazéns
 * diferentes, cada uma com seu número de lote. É a tela do funcionário 3,
 * que confere e ajusta o volume real — para mais ou para menos.
 */
class EntregaTest extends TestCase
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

        $fornecedor = Fornecedor::create(['nome' => 'LUIZ PEREIRA', 'documento' => '12345678000199']);
        $this->compra = Compra::create([
            'uts' => 'UTS 7312', 'data_compra' => '2026-08-10', 'fornecedor_id' => $fornecedor->id,
            'certificacao' => 'SEM_CERT', 'tipo_entrada' => 'BICA', 'logistica' => 'POSTO',
            'volume_contratado' => 500, 'valor_saca' => 1630, 'created_by' => $this->admin->id,
        ]);
    }

    private function lancar(array $dados = [])
    {
        return $this->actingAs($this->admin)->post(route('compras.entregas.store', $this->compra), array_merge([
            'mes_ano' => '2026-08', 'armazem' => 'QUALITE', 'volume_sacas' => 250, 'numero_lote' => '',
        ], $dados));
    }

    /** O caso que motivou tudo: 500 sacas, 250 hoje e 250 mês que vem. */
    public function test_a_mesma_uts_aceita_entregas_em_meses_e_armazens_diferentes(): void
    {
        $this->lancar(['mes_ano' => '2026-08', 'armazem' => 'QUALITE', 'volume_sacas' => 250])
            ->assertRedirect(route('compras.show', $this->compra));
        $this->lancar(['mes_ano' => '2026-09', 'armazem' => 'SAAG', 'volume_sacas' => 250]);

        $this->compra->refresh();
        $this->assertSame(2, $this->compra->entregas()->count());
        $this->assertEqualsWithDelta(500.0, $this->compra->sacasEntregues(), 0.01);
        $this->assertEqualsWithDelta(0.0, $this->compra->saldoAEntregar(), 0.01);
        $this->assertTrue($this->compra->totalmenteEntregue());
        $this->assertSame(['QUALITE', 'SAAG'], $this->compra->entregas->pluck('armazem')->sort()->values()->all());
    }

    public function test_saldo_a_entregar_aparece_enquanto_falta_volume(): void
    {
        $this->lancar(['volume_sacas' => 250]);

        $this->compra->refresh();
        $this->assertEqualsWithDelta(250.0, $this->compra->saldoAEntregar(), 0.01);
        $this->assertFalse($this->compra->totalmenteEntregue());
        $this->assertTrue(Compra::comSaldoAEntregar()->where('id', $this->compra->id)->exists());
    }

    /** O funcionário 3 ajusta o que chegou de verdade: 500 viraram 480. */
    public function test_volume_pode_ficar_abaixo_do_contratado(): void
    {
        $this->lancar(['volume_sacas' => 480])->assertSessionHasNoErrors();

        $this->compra->refresh();
        $this->assertEqualsWithDelta(480.0, $this->compra->sacasEntregues(), 0.01);
        $this->assertEqualsWithDelta(20.0, $this->compra->saldoAEntregar(), 0.01);
        // Paga-se pelo que entrou.
        $this->assertEqualsWithDelta(480 * 1630, $this->compra->valorEntregue(), 0.01);
        $this->assertEqualsWithDelta(500 * 1630, $this->compra->valorContratado(), 0.01);
    }

    /** …e pode chegar mais do que o contratado, sem o sistema recusar. */
    public function test_volume_pode_ficar_acima_do_contratado(): void
    {
        $this->lancar(['volume_sacas' => 520])->assertSessionHasNoErrors();

        $this->compra->refresh();
        $this->assertTrue($this->compra->entregouAMais());
        $this->assertEqualsWithDelta(-20.0, $this->compra->saldoAEntregar(), 0.01);
        $this->assertFalse(Compra::comSaldoAEntregar()->where('id', $this->compra->id)->exists());
    }

    public function test_mensagem_diz_quanto_falta_ou_avisa_excedente(): void
    {
        $this->lancar(['volume_sacas' => 200]);
        $this->assertStringContainsString('Faltam 300,00 sc', session('status'));

        $this->lancar(['volume_sacas' => 350]);
        $this->assertStringContainsString('50,00 sc a mais', session('status'));
    }

    public function test_entrega_sem_lote_nao_entra_no_estoque_definitivo(): void
    {
        $this->lancar(['volume_sacas' => 250, 'numero_lote' => '']);
        $this->lancar(['volume_sacas' => 250, 'numero_lote' => 'L-2026-01']);

        $this->assertSame(1, Entrega::semNumeroLote()->count());
        $this->assertSame(1, Entrega::comNumeroLote()->count());
    }

    /** O funcionário 3 volta depois e informa o lote. */
    public function test_editar_entrega_para_informar_o_lote(): void
    {
        $this->lancar(['volume_sacas' => 250]);
        $entrega = Entrega::first();
        $this->assertTrue($entrega->precisaDeNumeroLote());

        $this->actingAs($this->admin)->put(route('compras.entregas.update', [$this->compra, $entrega]), [
            'mes_ano' => '2026-08', 'armazem' => 'QUALITE', 'volume_sacas' => 245, 'numero_lote' => 'L-2026-77',
        ])->assertRedirect(route('compras.show', $this->compra));

        $entrega->refresh();
        $this->assertSame('L-2026-77', $entrega->numero_lote);
        $this->assertEqualsWithDelta(245.0, (float) $entrega->volume_sacas, 0.01);
    }

    public function test_excluir_entrega_recalcula_o_saldo(): void
    {
        $this->lancar(['volume_sacas' => 500]);
        $entrega = Entrega::first();
        $this->assertTrue($this->compra->refresh()->totalmenteEntregue());

        $this->actingAs($this->admin)
            ->delete(route('compras.entregas.destroy', [$this->compra, $entrega]))
            ->assertRedirect(route('compras.show', $this->compra));

        $this->compra->refresh();
        $this->assertSame(0, $this->compra->entregas()->count());
        $this->assertEqualsWithDelta(500.0, $this->compra->saldoAEntregar(), 0.01);
    }

    public function test_entrega_de_outra_compra_da_404(): void
    {
        $outra = Compra::create([
            'uts' => 'UTS 9999', 'data_compra' => '2026-08-10',
            'fornecedor_id' => $this->compra->fornecedor_id,
            'certificacao' => 'SEM_CERT', 'volume_contratado' => 100, 'created_by' => $this->admin->id,
        ]);
        $this->lancar(['volume_sacas' => 250]);
        $entrega = Entrega::first();

        $this->actingAs($this->admin)
            ->delete(route('compras.entregas.destroy', [$outra, $entrega]))
            ->assertNotFound();
    }

    public function test_campos_obrigatorios_da_entrega(): void
    {
        $this->actingAs($this->admin)
            ->post(route('compras.entregas.store', $this->compra), [])
            ->assertSessionHasErrors([
                'mes_ano' => 'Informe o mês/ano da entrega.',
                'armazem' => 'Selecione o armazém que recebeu o café.',
                'volume_sacas' => 'Informe quantas sacas entraram no armazém.',
            ]);
    }

    public function test_perfil_sem_permissao_nao_lanca_entrega(): void
    {
        $this->actingAs($this->diretoria)
            ->post(route('compras.entregas.store', $this->compra), [
                'mes_ano' => '2026-08', 'armazem' => 'SAAG', 'volume_sacas' => 100,
            ])
            ->assertForbidden();
    }

    public function test_tela_da_compra_mostra_contratado_entregue_e_saldo(): void
    {
        $this->lancar(['volume_sacas' => 250, 'armazem' => 'QUALITE']);

        $this->actingAs($this->admin)->get(route('compras.show', $this->compra))
            ->assertOk()
            ->assertSee('Contratado')
            ->assertSee('Entregue')
            ->assertSee('Falta entregar')
            ->assertSee('250,00 sc')
            ->assertSee('QUALITÉ')
            ->assertSee('sem número de lote');
    }
}
