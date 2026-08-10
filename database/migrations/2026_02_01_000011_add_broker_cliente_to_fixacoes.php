<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Além da NOSSA corretora, a fixação registra o broker que o CLIENTE usa
 * do lado dele (opcional — nem todo cliente informa). Lista fixa em
 * Fixacao::brokersCliente().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixacoes', function (Blueprint $table) {
            $table->string('broker_cliente', 40)->nullable()->after('corretora');
        });
    }

    public function down(): void
    {
        Schema::table('fixacoes', function (Blueprint $table) {
            $table->dropColumn('broker_cliente');
        });
    }
};
