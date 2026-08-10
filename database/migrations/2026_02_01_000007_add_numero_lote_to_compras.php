<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Número do lote (identificação dada pelo armazém/controle de estoque),
 * preenchido depois do lançamento da compra. Enquanto estiver em branco,
 * a compra não pode ser considerada definitivamente em estoque — a
 * interface avisa disso (ver Compra::precisaDeNumeroLote()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->string('numero_lote', 60)->nullable()->after('volume_sacas');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn('numero_lote');
        });
    }
};
