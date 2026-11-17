<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nota fiscal da entrega, separada do número do lote (nov/2026).
 *
 * A nota chega primeiro; a entrada no Digisul (que gera o lote) é lançada
 * depois. Enquanto isso o café está na conta fiscal e ainda NÃO está no
 * estoque físico — é a origem do retrabalho de conferência da mesa.
 *
 * O sistema já tratava isso: entrega sem `numero_lote` = "aguardando nº do
 * lote", fora do estoque definitivo. Mas a carga das compras gravou a NF
 * de entrada NO LUGAR do lote (a planilha fiscal não traz o lote) e com
 * isso toda nota virou estoque definitivo, inclusive as que ainda não
 * tinham entrada no Digisul.
 *
 * Com a NF em campo próprio, o lote volta a significar uma coisa só: o
 * lote do Digisul. Nota sem lote = entrada pendente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->string('nota_fiscal', 40)->nullable()->after('numero_lote');
        });
    }

    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->dropColumn('nota_fiscal');
        });
    }
};
