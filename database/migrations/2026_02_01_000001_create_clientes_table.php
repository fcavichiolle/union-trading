<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes (BUYER) dos contratos de exportação. Guardamos só o que o
 * contrato precisa: nome (1ª linha, em negrito no PDF) e um bloco de
 * endereço multilinha (razão social + rua + cidade/país/CEP), exatamente
 * como aparece no bloco BUYER do contrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150);
            $table->text('endereco');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
