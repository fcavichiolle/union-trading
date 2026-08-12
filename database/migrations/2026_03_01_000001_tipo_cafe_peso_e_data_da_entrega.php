<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Três mudanças pedidas pela mesa (ago/2026):
 *
 * 1. `compras.tipo_entrada` deixa de ser texto livre com "BICA" e passa a
 *    ser a ESPÉCIE do café: ARABICA (padrão) ou CONILON. É ela que decide
 *    se a compra tem padrão/bebida — conilon não é classificado em peneira
 *    de arábica, então esses campos ficam de fora.
 *
 * 2. Padrão final e tipo de bebida passam a ser informados já no
 *    lançamento da compra (são parte do negócio fechado, não da
 *    conferência posterior). Ficam nullable porque conilon não usa, e a
 *    classificação continua sendo a fonte da distribuição em peneiras.
 *
 * 3. PESO. O armazém às vezes informa sacas, às vezes quilos — os dois
 *    campos passam a existir lado a lado, cada um preenchendo o outro
 *    (60 kg por saca). Guardar os dois é de propósito: 200 sacas podem
 *    pesar 12.010 kg, e essa diferença é informação real, não erro de
 *    digitação.
 *
 * 4. A entrega passa a ter DATA COMPLETA (dia/mês/ano) em vez de mês/ano:
 *    a auditoria precisa saber o dia em que o café entrou. A coluna muda
 *    de nome (`mes_ano` -> `data_entrega`) porque um nome mentindo sobre a
 *    precisão do dado é o tipo de coisa que engana leitura futura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            // Espécie do café. O default cobre linhas antigas e o valor que
            // o formulário já traz pré-selecionado.
            $table->string('tipo_entrada', 40)->default('ARABICA')->change();

            $table->decimal('peso_kg', 14, 2)->nullable()->after('volume_contratado')
                ->comment('Peso contratado em kg (sacas x 60 quando não informado)');

            // Qualidade negociada. Nullable: conilon não tem, e compras
            // antigas foram lançadas antes destes campos existirem.
            $table->string('padrao_final', 40)->nullable()->after('peso_kg');
            $table->string('tipo_bebida', 40)->nullable()->after('padrao_final');
        });

        // "BICA" era o único valor possível antes e não é uma espécie —
        // todo o histórico é arábica.
        DB::table('compras')->where('tipo_entrada', 'BICA')->update(['tipo_entrada' => 'ARABICA']);

        // Padrão/bebida de quem já foi classificado sobem para a compra,
        // para as duas telas não mostrarem coisas diferentes.
        foreach (DB::table('classificacoes')->get() as $classificacao) {
            DB::table('compras')->where('id', $classificacao->compra_id)->update([
                'padrao_final' => $classificacao->padrao_final,
                'tipo_bebida' => $classificacao->tipo_bebida,
            ]);
        }

        Schema::table('entregas', function (Blueprint $table) {
            $table->decimal('peso_kg', 14, 2)->nullable()->after('volume_sacas')
                ->comment('Peso que entrou em kg (sacas x 60 quando não informado)');
        });

        Schema::table('entregas', function (Blueprint $table) {
            $table->renameColumn('mes_ano', 'data_entrega');
        });

        // O que já existe tem só o mês (dia 01) — fica como está: inventar
        // um dia seria pior do que admitir que aquele dado é do mês.
        DB::table('entregas')->whereNull('peso_kg')->update([
            'peso_kg' => DB::raw('volume_sacas * 60'),
        ]);

        DB::table('compras')->whereNull('peso_kg')->update([
            'peso_kg' => DB::raw('volume_contratado * 60'),
        ]);
    }

    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->renameColumn('data_entrega', 'mes_ano');
        });

        Schema::table('entregas', function (Blueprint $table) {
            $table->dropColumn('peso_kg');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn(['peso_kg', 'padrao_final', 'tipo_bebida']);
            $table->string('tipo_entrada', 40)->default('BICA')->change();
        });

        DB::table('compras')->where('tipo_entrada', 'ARABICA')->update(['tipo_entrada' => 'BICA']);
    }
};
