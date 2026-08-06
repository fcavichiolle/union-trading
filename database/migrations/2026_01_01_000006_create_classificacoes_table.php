<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase de seleção/classificação pós-entrega (Módulo 1, item 2).
 * 1 classificação por compra (relação 1:1).
 * quantidade_lotes é recalculada automaticamente pelo model
 * (Classificacao::boot) = total de sacas classificadas / 283.49.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classificacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->unique()->constrained('compras')->cascadeOnDelete();
            $table->enum('padrao_final', ['FINE_CUP', 'GOOD_CUP']);

            $table->decimal('peneira_1718_pct', 5, 2)->default(0);
            $table->decimal('peneira_1718_sacas', 12, 2)->default(0);

            $table->decimal('peneira_1416_pct', 5, 2)->default(0);
            $table->decimal('peneira_1416_sacas', 12, 2)->default(0);

            $table->decimal('mercado_interno_pct', 5, 2)->default(0);
            $table->decimal('mercado_interno_sacas', 12, 2)->default(0);

            $table->decimal('grinders_pct', 5, 2)->default(0);
            $table->decimal('grinders_sacas', 12, 2)->default(0);

            $table->decimal('quantidade_lotes', 12, 4)->default(0); // total_sacas / 283.49

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classificacoes');
    }
};
