<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Um contrato pode já nascer com o preço "FIXED" (valor absoluto acordado)
 * em vez de "a fixar" (fórmula com diferencial + mês de bolsa). Quando
 * fixado=true, `preco_fixado` é o valor exibido no campo PRICE
 * (Contrato::precoLinha()); quando false, o contrato usa diferencial/
 * mes_fixacao como já funcionava antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->boolean('fixado')->default(false)->after('mes_fixacao');
            $table->decimal('preco_fixado', 10, 2)->nullable()->after('fixado');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(['fixado', 'preco_fixado']);
        });
    }
};
