<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A unidade do preço FIXED (cts/lb ou USD/MT) é escolhida livremente —
 * é um valor negociado entre as partes, não a unidade "oficial" da bolsa
 * de referência do porto (essa continua fixa, usada só na fórmula "a
 * fixar": Santos=NY ICE=cents/pounds, Vitória=Robusta Londres=USD/MT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('preco_fixado_unidade', 10)->nullable()->after('preco_fixado');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('preco_fixado_unidade');
        });
    }
};
