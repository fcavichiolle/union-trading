<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Duas mudanças na classificação:
 *
 * 1. Padrões novos (VERY GOOD CUP e as três variações BICA). Em vez de
 *    ampliar o ENUM outra vez, a coluna `padrao_final` passa a ser
 *    VARCHAR NULL — a mesma decisão que já valia para `tipo_bebida`. Aceita
 *    nulo porque compra de CONILON não tem padrão de arábica. E sai do ENUM
 *    porque, com ENUM, cada padrão novo exige migration de ALTER que só roda no
 *    MySQL, e no SQLite dos testes o CHECK da coluna recusa os códigos
 *    novos — ou seja, era impossível TESTAR um padrão recém-adicionado
 *    (GOTCHA 3 do PROGRESSO). Agora a lista vive só em
 *    App\Models\Classificacao::padroes().
 *
 * 2. Faixas SCS 12 UP e SCS 13 UP, acima da 17/18. Toda faixa nova precisa
 *    ser somada em 4 lugares (model, request de validação, SQL do estoque
 *    e as tabelas que exibem) — está anotado no PROGRESSO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classificacoes', function (Blueprint $table) {
            $table->decimal('peneira_12up_pct', 5, 2)->default(0)->after('tipo_bebida');
            $table->decimal('peneira_12up_sacas', 12, 2)->default(0)->after('peneira_12up_pct');
            $table->decimal('peneira_13up_pct', 5, 2)->default(0)->after('peneira_12up_sacas');
            $table->decimal('peneira_13up_sacas', 12, 2)->default(0)->after('peneira_13up_pct');
        });

        $this->padraoFinalViraTexto();
    }

    public function down(): void
    {
        Schema::table('classificacoes', function (Blueprint $table) {
            $table->dropColumn([
                'peneira_12up_pct', 'peneira_12up_sacas',
                'peneira_13up_pct', 'peneira_13up_sacas',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE classificacoes MODIFY padrao_final ENUM('FINE_CUP','GOOD_CUP','GOOD_CUP_2R','RIO_MINAS') NOT NULL");
        }
    }

    /**
     * ENUM -> VARCHAR sem perder dado, nos dois bancos.
     *
     * No SQLite o ENUM da criação virou um CHECK amarrado à definição da
     * tabela, e `->change()` não garante que ele caia; recriar a coluna
     * (copiando os valores) é o caminho previsível.
     */
    private function padraoFinalViraTexto(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE classificacoes MODIFY padrao_final VARCHAR(40) NULL');

            return;
        }

        Schema::table('classificacoes', function (Blueprint $table) {
            $table->string('padrao_final_novo', 40)->nullable();
        });

        DB::statement('update classificacoes set padrao_final_novo = padrao_final');

        Schema::table('classificacoes', function (Blueprint $table) {
            $table->dropColumn('padrao_final');
        });

        Schema::table('classificacoes', function (Blueprint $table) {
            $table->renameColumn('padrao_final_novo', 'padrao_final');
        });
    }
};
