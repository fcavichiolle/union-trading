<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baixa de estoque do embarque (nov/2026).
 *
 * Faturar e dar baixa são coisas diferentes. Faturar é emitir a nota; dar
 * baixa é registrar a SAÍDA do café do estoque (no Digisul). Entre uma e
 * outra o café continua no armazém — e continua no estoque físico que o
 * Digisul informa.
 *
 * Até aqui o sistema tirava a venda da posição ao faturar. Com o estoque
 * físico de um lado e as vendas do outro, isso descasava a conta: o café
 * faturado e ainda não baixado contava no estoque, mas a venda dele já
 * tinha sumido — a posição ficava mais comprada do que é.
 *
 * A baixa é uma DATA, não uma etapa: ela não tem ordem fixa com a nota
 * (a saída pode ser registrada antes ou depois do faturamento), então não
 * cabe na fila de status do embarque.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embarques', function (Blueprint $table) {
            $table->date('baixado_em')->nullable()->after('faturado_em');
            $table->index('baixado_em');
        });
    }

    public function down(): void
    {
        Schema::table('embarques', function (Blueprint $table) {
            $table->dropIndex(['baixado_em']);
            $table->dropColumn('baixado_em');
        });
    }
};
