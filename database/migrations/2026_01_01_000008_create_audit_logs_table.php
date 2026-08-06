<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha de auditoria para ações sensíveis de segurança:
 * login, login falho, criação/edição de usuário, troca de senha,
 * troca de perfil de acesso, desativação de conta, etc.
 * Isso é o que permite responder "quem fez o quê e quando" numa
 * revisão de segurança do sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acao', 80); // ex: login_sucesso, login_falho, usuario_criado...
            $table->string('descricao', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
