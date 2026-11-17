<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `fixacoes.corretora` cabia 30 caracteres e `broker_cliente` 40 — medidas
 * da época em que esses campos guardavam CÓDIGOS ('STONEX', 'ICAP'). Quando
 * as corretoras viraram cadastro, a fixação passou a gravar o NOME como
 * snapshot, e `corretoras.nome` aceita 80.
 *
 * Resultado: escolher uma corretora de nome longo derrubava a tela com
 * "Data too long for column 'corretora'" no INSERT — duas das três nossas
 * corretoras estouravam ("ICAP Corporates LLC (Hedgepoint)" tem 32 e
 * "Marex Financial Limited AGS Coffee" tem 34). Só a StoneX cabia, que por
 * acaso era a usada nos testes.
 *
 * As duas colunas passam a ter o mesmo tamanho do cadastro de origem: o
 * snapshot nunca pode ser menor do que aquilo que ele copia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixacoes', function (Blueprint $table) {
            $table->string('corretora', 80)->change();
            $table->string('broker_cliente', 80)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('fixacoes', function (Blueprint $table) {
            $table->string('corretora', 30)->change();
            $table->string('broker_cliente', 40)->nullable()->change();
        });
    }
};
