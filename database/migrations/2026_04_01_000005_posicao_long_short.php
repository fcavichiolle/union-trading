<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posição Long & Short (nov/2026) — passo 3 do roteiro da posição.
 *
 * Na planilha a conta é: estoque por padrão (medido + rendimento estimado
 * do que ainda é bica) + compras que ainda não estão no estoque − vendas
 * por mês. O que faltava no sistema para fazer a mesma conta:
 *
 *  1. A FOTO DO ESTOQUE. O estoque físico é do Digisul (é ele que dá
 *     entrada e baixa); o sistema guarda uma foto dele, lote a lote, com a
 *     data em que foi tirada. Dali para a frente o sistema anda sozinho:
 *     soma as entregas e tira as baixas posteriores à foto. Uma foto nova
 *     só re-ancora a conta.
 *     Cada lote guarda o PADRÃO do Digisul e o BALDE da posição (17/18 FC,
 *     14/16 GC, bica, reaproveitamento…) — o de/para é do sistema, mas fica
 *     gravado no lote para a foto antiga não mudar se a regra mudar.
 *
 *  2. O RENDIMENTO PREVISTO DA COMPRA: % de 17/18 e % de cata, combinados
 *     na amostra. É o que transforma "500 sacas de bica" em sacas de cada
 *     padrão. Fica na compra (e não só na classificação) porque existe
 *     antes de o café chegar — compra a receber também entra na posição.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fotos_estoque', function (Blueprint $table) {
            $table->id();
            $table->date('data_foto');
            $table->string('origem', 120)->nullable();
            $table->text('observacao')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('data_foto');
        });

        Schema::create('foto_estoque_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foto_estoque_id')->constrained('fotos_estoque')->cascadeOnDelete();
            $table->string('lote', 60);
            $table->string('armazem', 80)->nullable();
            $table->string('padrao', 40)->nullable();   // como vem do Digisul
            $table->string('balde', 20)->nullable();    // FotoEstoque::baldes()
            $table->string('peneira', 160)->nullable();
            $table->string('bebida', 40)->nullable();
            $table->decimal('kg', 12, 2)->default(0);
            $table->decimal('sacas', 12, 2)->default(0); // kg ÷ 60, como na planilha

            $table->index(['foto_estoque_id', 'balde']);
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->decimal('pct_1718', 5, 2)->nullable()->after('tipo_bebida');
            $table->decimal('pct_cata', 5, 2)->nullable()->after('pct_1718');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn(['pct_1718', 'pct_cata']);
        });

        Schema::dropIfExists('foto_estoque_lotes');
        Schema::dropIfExists('fotos_estoque');
    }
};
