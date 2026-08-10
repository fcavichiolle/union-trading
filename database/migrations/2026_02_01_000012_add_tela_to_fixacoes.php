<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Tela" = a posição da bolsa contra a qual a fixação foi feita
 * (ex.: Z6 no NY ICE, Sep_2026 em Londres). Os códigos são os mesmos de
 * Contrato::mesesFixacaoSantos()/mesesFixacaoVitoria(), conforme o porto
 * do contrato. Nullable só por causa de fixações antigas — o formulário
 * exige o campo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixacoes', function (Blueprint $table) {
            $table->string('tela', 20)->nullable()->after('broker_cliente');
        });
    }

    public function down(): void
    {
        Schema::table('fixacoes', function (Blueprint $table) {
            $table->dropColumn('tela');
        });
    }
};
