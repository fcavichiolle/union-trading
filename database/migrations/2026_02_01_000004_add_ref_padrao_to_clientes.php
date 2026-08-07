<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alguns clientes têm uma referência de comprador fixa que deve aparecer
 * em todo contrato (ex.: MIORI -> "CONTRACT NO. 26-003 DD. 17.02.2026").
 * Guardamos isso no cliente e o formulário preenche o Buyer Ref sozinho
 * ao selecioná-lo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('ref_padrao', 120)->nullable()->after('endereco');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('ref_padrao');
        });
    }
};
