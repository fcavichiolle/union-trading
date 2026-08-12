<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duas coisas no canal de mensagens:
 *
 * 1. TEXTO CIFRADO NO BANCO. `mensagens.texto` passa a guardar o payload
 *    criptografado (cast `encrypted` do model, AES-256-CBC com a APP_KEY),
 *    então a coluna deixa de ser VARCHAR(2000) e vira TEXT — o cifrado é
 *    bem maior que o texto puro (base64 + IV + MAC).
 *
 *    O que isso protege: dump/backup do banco e acesso só ao MySQL — que é
 *    onde dado vaza na prática. O que NÃO protege: quem tem servidor +
 *    APP_KEY lê tudo (a tela precisa descriptografar), e o trânsito é
 *    responsabilidade do HTTPS. Consequências assumidas: a APP_KEY fica
 *    insubstituível (perdê-la = perder as mensagens) e não existe busca
 *    por conteúdo em SQL.
 *
 * 2. MENÇÕES. `mensagem_mencoes` liga mensagem => usuário mencionado. É
 *    tabela, e não busca no texto na hora de ler, por dois motivos: com o
 *    texto cifrado o banco não sabe procurar "@Fulano", e a contagem de
 *    menções não lidas precisa ser uma query rápida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensagens', function (Blueprint $table) {
            // Cabe o cifrado de uma mensagem de 2.000 caracteres com folga.
            $table->text('texto')->change();
        });

        Schema::create('mensagem_mencoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensagem_id')->constrained('mensagens')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            // Mencionar duas vezes a mesma pessoa na mesma mensagem é um
            // aviso só.
            $table->unique(['mensagem_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagem_mencoes');

        Schema::table('mensagens', function (Blueprint $table) {
            $table->string('texto', 2000)->change();
        });
    }
};
