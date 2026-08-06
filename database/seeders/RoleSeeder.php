<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['slug' => 'admin', 'nome' => 'Administrador', 'descricao' => 'Acesso total ao sistema, incluindo gestão de usuários.'],
            ['slug' => 'compras', 'nome' => 'Compras e Classificação', 'descricao' => 'Lança compras, classifica lotes e vê relatórios.'],
            ['slug' => 'financeiro', 'nome' => 'Financeiro', 'descricao' => 'Lança/edita dados financeiros das compras.'],
            ['slug' => 'diretoria', 'nome' => 'Diretoria', 'descricao' => 'Acesso somente leitura aos relatórios/dashboards.'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
