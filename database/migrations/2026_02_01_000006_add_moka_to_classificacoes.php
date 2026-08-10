<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nova faixa de classificação "Moka", ao lado de SCS 17/18, SCS 14/16,
 * Mercado interno e Grinders. Migration de ALTER (não editar a migration
 * de criação original — ela já rodou em ambientes existentes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classificacoes', function (Blueprint $table) {
            $table->decimal('moka_pct', 5, 2)->default(0)->after('grinders_sacas');
            $table->decimal('moka_sacas', 12, 2)->default(0)->after('moka_pct');
        });
    }

    public function down(): void
    {
        Schema::table('classificacoes', function (Blueprint $table) {
            $table->dropColumn(['moka_pct', 'moka_sacas']);
        });
    }
};
