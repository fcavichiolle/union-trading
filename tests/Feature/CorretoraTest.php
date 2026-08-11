<?php

namespace Tests\Feature;

use App\Models\Corretora;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CorretoraTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $compras;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $compras = Role::create(['slug' => 'compras', 'nome' => 'Compras']);

        $this->admin = User::create([
            'role_id' => $admin->id, 'name' => 'Admin', 'email' => 'admin@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
        $this->compras = User::create([
            'role_id' => $compras->id, 'name' => 'Compras', 'email' => 'compras@teste.com',
            'password' => Hash::make('x'), 'force_password_change' => false, 'active' => true,
        ]);
    }

    public function test_migration_semeia_as_listas_que_eram_fixas_no_codigo(): void
    {
        $this->assertSame(3, Corretora::nossas()->count());
        $this->assertSame(8, Corretora::doCliente()->count());
        $this->assertTrue(Corretora::nossas()->where('nome', 'StoneX East Coast')->exists());
        $this->assertTrue(Corretora::doCliente()->where('nome', 'Macquarie USA')->exists());
    }

    public function test_admin_cria_renomeia_e_exclui_corretora(): void
    {
        // Criar
        $this->actingAs($this->admin)
            ->post(route('admin.corretoras.store'), ['nome' => 'Nova Corretora XP', 'tipo' => 'NOSSA'])
            ->assertRedirect(route('admin.corretoras.index'));
        $c = Corretora::where('nome', 'Nova Corretora XP')->first();
        $this->assertSame('NOSSA', $c->tipo);

        // Renomear (o tipo não muda pelo update)
        $this->actingAs($this->admin)
            ->put(route('admin.corretoras.update', $c), ['nome' => 'Corretora XP Renomeada'])
            ->assertRedirect(route('admin.corretoras.index'));
        $this->assertSame('Corretora XP Renomeada', $c->fresh()->nome);
        $this->assertSame('NOSSA', $c->fresh()->tipo);

        // Excluir
        $this->actingAs($this->admin)->delete(route('admin.corretoras.destroy', $c));
        $this->assertNull(Corretora::find($c->id));
    }

    public function test_nome_duplicado_no_mesmo_tipo_e_recusado_mas_pode_repetir_em_tipo_diferente(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.corretoras.store'), ['nome' => 'StoneX East Coast', 'tipo' => 'NOSSA'])
            ->assertSessionHasErrors('nome'); // já existe como NOSSA (seed)

        // "Stonex East Coast" já existe como CLIENTE (seed) — como NOSSA pode.
        $this->actingAs($this->admin)
            ->post(route('admin.corretoras.store'), ['nome' => 'Sucden London', 'tipo' => 'NOSSA'])
            ->assertSessionHasNoErrors();
    }

    public function test_perfil_nao_admin_recebe_403(): void
    {
        $this->actingAs($this->compras)->get(route('admin.corretoras.index'))->assertForbidden();
        $this->actingAs($this->compras)->post(route('admin.corretoras.store'), ['nome' => 'X', 'tipo' => 'NOSSA'])->assertForbidden();
    }

    public function test_tela_ny_usa_o_cadastro_nos_dropdowns(): void
    {
        Corretora::create(['nome' => 'Corretora Recém-criada', 'tipo' => 'NOSSA']);
        Corretora::create(['nome' => 'Broker Recém-criado', 'tipo' => 'CLIENTE']);

        $this->actingAs($this->admin)->get(route('ny.index'))
            ->assertOk()
            ->assertSee('Corretora Recém-criada')
            ->assertSee('Broker Recém-criado');
    }

    public function test_excluir_corretora_nao_altera_fixacao_antiga(): void
    {
        // Fixação gravada com o nome (snapshot)…
        $cliente = \App\Models\Cliente::create(['nome' => 'ICONA', 'endereco' => 'Madrid']);
        $qualidade = \App\Models\Qualidade::create(['descricao' => 'CAFÉ 14/16']);
        $contrato = \App\Models\Contrato::create([
            'numero_ut' => '5940', 'data_contrato' => '2026-08-05',
            'cliente_id' => $cliente->id, 'cliente_nome' => $cliente->nome, 'cliente_endereco' => $cliente->endereco,
            'qualidade_id' => $qualidade->id, 'qualidade_descricao' => $qualidade->descricao,
            'tipo_cafe' => 'ARABICA', 'certificado' => 'RFA', 'quantidade_kg' => 108000,
            'tipo_container' => '40', 'embalagem' => 'Bulk Liner', 'incoterms' => 'FOB', 'porto' => 'SANTOS',
            'created_by' => $this->admin->id,
        ]);
        $fixacao = \App\Models\Fixacao::create([
            'contrato_id' => $contrato->id, 'corretora' => 'StoneX East Coast',
            'tela' => 'Z6', 'lotes' => 2, 'level' => 335, 'diferencial' => -16,
        ]);

        // …a corretora sai do cadastro…
        Corretora::nossas()->where('nome', 'StoneX East Coast')->first()->delete();

        // …e a fixação continua com o nome da época.
        $this->assertSame('StoneX East Coast', $fixacao->fresh()->corretora);
    }
}
