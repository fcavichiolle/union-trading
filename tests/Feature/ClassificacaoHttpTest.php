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
 * Regras de negócio da classificação exercidas pela rota (backend), pois
 * validação só no navegador pode ser burlada por quem envia a requisição
 * direto: a soma das porcentagens deve fechar 100% e a soma das sacas não
 * pode ultrapassar o volume comprado.
 */
class ClassificacaoHttpTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'padrao_final' => 'FINE_CUP',
            'peneira_1718_pct' => 50, 'peneira_1718_sacas' => 150,
            'peneira_1416_pct' => 30, 'peneira_1416_sacas' => 100,
            'mercado_interno_pct' => 10, 'mercado_interno_sacas' => 30,
            'grinders_pct' => 10, 'grinders_sacas' => 20,
        ], $overrides);
    }

    public function test_classificacao_valida_e_salva_e_calcula_os_lotes(): void
    {
        $resposta = $this->actingAs($this->admin)
            ->put(route('compras.classificacao.update', $this->compra), $this->payload());

        $resposta->assertRedirect(route('compras.show', $this->compra));
        $resposta->assertSessionHasNoErrors();

        $this->assertDatabaseHas('classificacoes', [
            'compra_id' => $this->compra->id,
            'padrao_final' => 'FINE_CUP',
        ]);

        $lotes = (float) $this->compra->fresh()->classificacao->quantidade_lotes;
        $this->assertEqualsWithDelta(round(300 / 283.49, 4), $lotes, 0.00001);
    }

    public function test_soma_das_porcentagens_deve_fechar_em_100(): void
    {
        // 50 + 30 + 5 + 5 = 90% (sacas continuam válidas)
        $resposta = $this->actingAs($this->admin)->put(
            route('compras.classificacao.update', $this->compra),
            $this->payload(['mercado_interno_pct' => 5, 'grinders_pct' => 5])
        );

        $resposta->assertSessionHasErrors('peneira_1718_pct');
        $this->assertDatabaseCount('classificacoes', 0);
    }

    public function test_soma_das_sacas_nao_pode_ultrapassar_o_volume_da_compra(): void
    {
        // 200 + 100 + 60 + 40 = 400 sacas > volume 300 (porcentagens fecham 100)
        $resposta = $this->actingAs($this->admin)->put(
            route('compras.classificacao.update', $this->compra),
            $this->payload([
                'peneira_1718_sacas' => 200,
                'peneira_1416_sacas' => 100,
                'mercado_interno_sacas' => 60,
                'grinders_sacas' => 40,
            ])
        );

        $resposta->assertSessionHasErrors('peneira_1718_sacas');
        $this->assertDatabaseCount('classificacoes', 0);
    }

    public function test_usuario_sem_permissao_nao_acessa(): void
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
            ->put(route('compras.classificacao.update', $this->compra), $this->payload());

        $resposta->assertForbidden();
        $this->assertDatabaseCount('classificacoes', 0);
    }
}
