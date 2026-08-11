<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de bebida da classificação (DURO, DURO + 1RY, RIO...).
 *
 * Coluna VARCHAR de propósito, não ENUM: `padrao_final` e `certificacao`
 * são ENUM e por isso exigem migration de ALTER toda vez que a lista muda
 * (ver GOTCHA 3 no PROGRESSO — se só o model mudar, o MySQL trunca o
 * valor). Com VARCHAR, incluir um tipo novo é editar
 * `Classificacao::tiposBebida()` e mais nada.
 *
 * Nullable porque classificações antigas não têm o dado; o formulário
 * exige o campo (StoreClassificacaoRequest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classificacoes', function (Blueprint $table) {
            $table->string('tipo_bebida', 30)->nullable()->after('padrao_final');
        });
    }

    public function down(): void
    {
        Schema::table('classificacoes', function (Blueprint $table) {
            $table->dropColumn('tipo_bebida');
        });
    }
};
