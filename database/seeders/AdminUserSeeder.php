<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Como NÃO existe tela pública de cadastro, é preciso um jeito de criar
 * o primeiro usuário administrador. Este seeder resolve isso — deve ser
 * rodado UMA VEZ na instalação (php artisan db:seed) e a senha gerada
 * deve ser trocada no primeiro login (force_password_change cuida disso).
 *
 * Defina ADMIN_EMAIL e, opcionalmente, ADMIN_PASSWORD no seu .env antes
 * de rodar o seeder. Se ADMIN_PASSWORD não for definida, uma senha
 * aleatória forte é gerada e impressa no terminal.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('slug', 'admin')->firstOrFail();

        $email = env('ADMIN_EMAIL', 'admin@uniontrading.com.br');

        if (User::where('email', $email)->exists()) {
            $this->command?->warn("Usuário admin '{$email}' já existe. Nada foi alterado.");
            return;
        }

        $senha = env('ADMIN_PASSWORD') ?: Str::password(18);

        User::create([
            'role_id' => $adminRole->id,
            'name' => 'Administrador Union Trading',
            'email' => $email,
            'password' => Hash::make($senha),
            'force_password_change' => true,
            'active' => true,
        ]);

        $this->command?->info("Usuário admin criado: {$email}");
        $this->command?->info("Senha temporária (anote agora, não será mostrada de novo): {$senha}");
    }
}
