<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Fornecedor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Uma compra só pode ser considerada definitivamente em estoque quando
 * tem um número de lote associado. Enquanto estiver em branco, a
 * interface (lista "Compras lançadas" e a tela da compra) mostra um alerta.
 */
class NumeroLoteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Compra $compra;

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
        $fornecedor = Fornecedor::create(['nome' => 'Fazenda Teste', 'cnpj' => '00.000.000/0001-00']);
        $this->compra = Compra::create([
            'uts' => 'UTS-2026-TESTE',
            'mes_ano' => '2026-01-01',
            'fornecedor_id' => $fornecedor->id,
            'armazem' => 'SAAG',
            'certificacao' => 'SEM_CERT',
            'volume_sacas' => 300,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_compra_nova_precisa_de_numero_de_lote(): void
    {
        $this->assertTrue($this->compra->precisaDeNumeroLote());
    }

    public function test_compra_com_numero_de_lote_nao_precisa_mais(): void
    {
        $this->compra->update(['numero_lote' => 'L-2026-0001']);

        $this->assertFalse($this->compra->fresh()->precisaDeNumeroLote());
    }

    public function test_alerta_aparece_na_lista_quando_falta_o_lote(): void
    {
        $resposta = $this->actingAs($this->admin)->get(route('compras.index'));

        $resposta->assertOk();
        $resposta->assertSee('Falta nº do lote', false);
    }

    public function test_alerta_nao_aparece_na_lista_quando_tem_lote(): void
    {
        $this->compra->update(['numero_lote' => 'L-2026-0001']);

        $resposta = $this->actingAs($this->admin)->get(route('compras.index'));

        $resposta->assertOk();
        $resposta->assertDontSee('Falta nº do lote');
        $resposta->assertSee('L-2026-0001');
    }

    public function test_alerta_aparece_na_tela_da_compra_quando_falta_o_lote(): void
    {
        $resposta = $this->actingAs($this->admin)->get(route('compras.show', $this->compra));

        $resposta->assertOk();
        $resposta->assertSee('Falta o número do lote');
    }

    public function test_alerta_nao_aparece_na_tela_da_compra_quando_tem_lote(): void
    {
        $this->compra->update(['numero_lote' => 'L-2026-0001']);

        $resposta = $this->actingAs($this->admin)->get(route('compras.show', $this->compra));

        $resposta->assertOk();
        $resposta->assertDontSee('Falta o número do lote');
    }

    public function test_salvar_numero_do_lote(): void
    {
        $resposta = $this->actingAs($this->admin)
            ->put(route('compras.lote.update', $this->compra), ['numero_lote' => 'L-2026-0451']);

        $resposta->assertRedirect(route('compras.show', $this->compra));
        $this->assertSame('L-2026-0451', $this->compra->fresh()->numero_lote);
    }

    public function test_numero_do_lote_e_obrigatorio_ao_salvar(): void
    {
        $resposta = $this->actingAs($this->admin)
            ->put(route('compras.lote.update', $this->compra), ['numero_lote' => '']);

        $resposta->assertSessionHasErrors('numero_lote');
        $this->assertNull($this->compra->fresh()->numero_lote);
    }

    public function test_usuario_sem_permissao_nao_atualiza_o_lote(): void
    {
        $roleDiretoria = Role::create(['slug' => 'diretoria', 'nome' => 'Diretoria']);
        $diretor = User::create([
            'role_id' => $roleDiretoria->id,
            'name' => 'Diretor',
            'email' => 'diretor@teste.com',
            'password' => Hash::make('senha-teste'),
            'force_password_change' => false,
            'active' => true,
        ]);

        $resposta = $this->actingAs($diretor)
            ->put(route('compras.lote.update', $this->compra), ['numero_lote' => 'L-2026-0451']);

        $resposta->assertForbidden();
        $this->assertNull($this->compra->fresh()->numero_lote);
    }
}
