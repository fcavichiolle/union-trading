<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reações às mensagens do canal da equipe (nov/2026).
 *
 * Uma linha por (mensagem, pessoa, emoji): a mesma pessoa pode reagir com
 * mais de um emoji, mas não duas vezes com o mesmo — o índice único é o que
 * garante isso mesmo com dois cliques simultâneos.
 *
 * O emoji NÃO é cifrado como o texto: é um de uma lista fixa
 * (`Mensagem::REACOES`), não é conteúdo escrito por usuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagem_reacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensagem_id')->constrained('mensagens')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();

            $table->unique(['mensagem_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagem_reacoes');
    }
};
