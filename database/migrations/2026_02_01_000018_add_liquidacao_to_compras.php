<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LIQUIDAÇÃO da compra: aceita o volume entregue como o valor final.
 *
 * O armazém raramente recebe exatamente o contratado — vêm 260 no lugar de
 * 250, ou 240 e ficou por isso mesmo. Enquanto ninguém decide, o sistema
 * avisa da diferença (pode faltar café a receber de verdade). Quando a
 * pessoa liquida, o negócio é considerado encerrado com o que entrou e os
 * avisos param.
 *
 * O `volume_contratado` NÃO é sobrescrito de propósito: a diferença entre o
 * negociado e o recebido é informação real (quebra ou excedente) e continua
 * visível na tela da compra. A liquidação é uma decisão registrada — com
 * data e autor — não um apagamento do histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->timestamp('liquidada_em')->nullable()->after('pagamento_obs');
            $table->foreignId('liquidada_por')->nullable()->after('liquidada_em')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('liquidada_por');
            $table->dropColumn('liquidada_em');
        });
    }
};
