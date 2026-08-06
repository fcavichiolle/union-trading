<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financeiro da compra (Módulo 1, item 3). 1:1 com compras.
 * valor_total é recalculado automaticamente pelo model = valor_saca * volume_sacas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financeiro_compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->unique()->constrained('compras')->cascadeOnDelete();
            $table->decimal('valor_saca', 12, 2);
            $table->decimal('valor_total', 14, 2)->default(0);
            $table->string('corretor_nome', 150)->nullable();
            $table->decimal('comissao_pct', 5, 2)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financeiro_compras');
    }
};
