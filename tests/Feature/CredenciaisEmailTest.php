<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\CredenciaisDeAcesso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Ao criar um usuário ou resetar a senha, o sistema envia as credenciais
 * (senha temporária) por e-mail — não depende mais só da tela do admin.
 */
class CredenciaisEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Role $roleAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleAdmin = Role::create(['slug' => 'admin', 'nome' => 'Administrador']);
        $this->admin = User::create([
            'role_id' => $this->roleAdmin->id,
            'name' => 'Admin Teste',
            'email' => 'admin@teste.com',
            'password' => Hash::make('senha-teste'),
            'force_password_change' => false,
            'active' => true,
        ]);
    }

    public function test_criar_usuario_envia_credenciais_por_email(): void
    {
        Notification::fake();

        $resposta = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Novo Colaborador',
            'email' => 'novo@utrading.com.br',
            'role_id' => $this->roleAdmin->id,
        ]);

        $resposta->assertRedirect(route('admin.users.index'));

        $novo = User::where('email', 'novo@utrading.com.br')->firstOrFail();
        $this->assertTrue($novo->force_password_change);

        Notification::assertSentTo(
            $novo,
            CredenciaisDeAcesso::class,
            fn (CredenciaisDeAcesso $n) => $n->isReset === false && $n->senhaTemporaria !== ''
        );
    }

    public function test_resetar_senha_envia_credenciais_por_email(): void
    {
        Notification::fake();

        $alvo = User::create([
            'role_id' => $this->roleAdmin->id,
            'name' => 'Usuário Alvo',
            'email' => 'alvo@utrading.com.br',
            'password' => Hash::make('antiga-senha'),
            'force_password_change' => false,
            'active' => true,
        ]);

        $resposta = $this->actingAs($this->admin)
            ->patch(route('admin.users.reset-password', $alvo));

        $resposta->assertRedirect();

        Notification::assertSentTo(
            $alvo,
            CredenciaisDeAcesso::class,
            fn (CredenciaisDeAcesso $n) => $n->isReset === true
        );

        // A troca no primeiro acesso volta a ser obrigatória.
        $this->assertTrue($alvo->fresh()->force_password_change);
    }
}
