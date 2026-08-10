<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixações de preço feitas na "Tela NY": um contrato A FIXAR pode ser
 * fixado em várias tranches (por lotes). Cada linha registra a corretora,
 * quantos lotes foram fixados, o level da bolsa e o diferencial — o preço
 * da tranche (level + diferencial) é calculado no servidor. Quando a soma
 * dos lotes fixados atinge os lotes do contrato, o contrato vira FIXED
 * com preço = média ponderada das tranches (ver Contrato::recalcularFixacao()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->string('corretora', 30);
            $table->unsignedInteger('lotes');
            $table->decimal('level', 10, 2);       // preço da bolsa no momento da fixação
            $table->decimal('diferencial', 10, 2); // diferencial negociado (pode ser negativo)
            $table->decimal('preco', 10, 2);       // level + diferencial — sempre recalculado no servidor
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('fixacoes');
    }
};
