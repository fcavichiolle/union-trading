<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de entrada de compra (Módulo 1, item 1).
 * O padrão final (Fine Cup / Good Cup) e a distribuição em peneiras só
 * existem depois da classificação -> ficam na tabela `classificacoes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('uts', 60)->unique()->comment('Referência única da compra (UTS)');
            $table->date('mes_ano'); // salvo como primeiro dia do mês (input type=month)
            $table->foreignId('fornecedor_id')->constrained('fornecedores')->restrictOnDelete();
            $table->enum('armazem', ['SAAG', 'QUALITE', 'DINAMO_MACHADO']);
            $table->enum('certificacao', ['SEM_CERT', '4C', 'RFA', 'EUDR', '4C_EUDR', 'RFA_EUDR', '4C_RFA']);
            $table->string('tipo_entrada', 40)->default('BICA'); // assumido na criação
            $table->decimal('volume_sacas', 12, 2);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('mes_ano');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
