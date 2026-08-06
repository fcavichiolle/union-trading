<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuários do sistema. NÃO existe fluxo de auto-cadastro: todo usuário
 * é criado por um administrador (ver Admin\UserController).
 *
 * force_password_change: obriga o usuário a trocar a senha temporária
 * gerada pelo admin no primeiro acesso (evita senha "de origem" ficar
 * valendo para sempre).
 *
 * active: em vez de excluir usuários (o que apaga rastro de auditoria
 * em compras/registros ligados a ele), o admin desativa o acesso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('email', 150)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password'); // sempre hash (bcrypt), nunca texto puro
            $table->boolean('force_password_change')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
