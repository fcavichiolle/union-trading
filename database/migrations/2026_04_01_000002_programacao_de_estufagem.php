<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programação de estufagem (nov/2026).
 *
 * A cada embarque a logística manda um e-mail de programação para o
 * armazém e a transportadora, com uma tabela de datas e uma segunda tabela
 * para a empresa de fumigação. Era montado à mão, copiando da planilha.
 * Agora o sistema gera um PDF (o armazém imprime) e abre o e-mail com
 * destinatários e assunto prontos.
 *
 * O que faltava no embarque para isso: ICO, os dados da fumigação
 * (empresa, tipo, tempo, permit import) e as instruções específicas do
 * embarque ("utilizar kit forração"). E os destinatários, que viram
 * cadastro: e-mails no armazém e um cadastro novo de transportadoras.
 *
 * O embarque continua guardando o NOME da transportadora como texto (igual
 * à corretora na fixação): o cadastro serve para sugerir o nome e achar os
 * e-mails, e renomear/excluir nele não mexe no histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embarques', function (Blueprint $table) {
            $table->string('ico', 40)->nullable()->after('pilha');
            $table->string('fumigacao_empresa', 60)->nullable()->after('data_fumigacao');
            $table->string('fumigacao_tipo', 60)->nullable()->after('fumigacao_empresa');
            $table->string('fumigacao_tempo', 20)->nullable()->after('fumigacao_tipo');
            $table->string('permit_import', 40)->nullable()->after('fumigacao_tempo');
            $table->text('instrucoes')->nullable()->after('observacoes');
        });

        Schema::table('armazens', function (Blueprint $table) {
            $table->text('emails_programacao')->nullable();
        });

        Schema::create('transportadoras', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 60)->unique();
            $table->text('emails')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transportadoras');

        Schema::table('armazens', function (Blueprint $table) {
            $table->dropColumn('emails_programacao');
        });

        Schema::table('embarques', function (Blueprint $table) {
            $table->dropColumn(['ico', 'fumigacao_empresa', 'fumigacao_tipo', 'fumigacao_tempo', 'permit_import', 'instrucoes']);
        });
    }
};
