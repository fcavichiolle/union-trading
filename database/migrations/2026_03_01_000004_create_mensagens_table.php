<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canal geral de mensagens da equipe (mural interno).
 *
 * Escolhas:
 *  - **Sem WebSocket.** A tela pergunta por mensagens novas de tempo em
 *    tempo (polling), como a página de Cotações já faz com /api/market.
 *    Tempo real de verdade exigiria um processo rodando sempre ao lado do
 *    PHP (Reverb/supervisor) e o projeto não tem nem worker de fila — o
 *    custo de deploy não se paga para uma conversa de escritório.
 *  - **Não lidas por marca de leitura, não por tabela de leituras.**
 *    `users.mensagens_lidas_em` guarda quando o usuário abriu o canal pela
 *    última vez; não lidas = mensagens de outros criadas depois disso.
 *    Com um canal só, uma tabela pivô de leitura por mensagem seria peso
 *    sem ganho.
 *  - `user_id` com restrictOnDelete, como o resto do sistema: usuário não é
 *    excluído (é suspenso pelo campo `active`), e assim ninguém apaga o
 *    autor de um histórico por acidente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('texto', 2000);
            $table->timestamps();

            // A tela lê sempre as mais recentes.
            $table->index('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('mensagens_lidas_em')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mensagens_lidas_em');
        });

        Schema::dropIfExists('mensagens');
    }
};
