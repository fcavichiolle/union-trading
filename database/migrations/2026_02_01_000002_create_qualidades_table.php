<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qualidades de café usadas no campo QUALITY do contrato
 * (ex.: "CAFÉ ARÁB NAT BRASIL CRIBA 14/16 SS GC"). É só uma descrição
 * de texto; se é arábica ou conilon é escolhido POR CONTRATO (afeta o
 * divisor de lotes), não fica preso à qualidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualidades', function (Blueprint $table) {
            $table->id();
            $table->string('descricao', 200);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualidades');
    }
};
