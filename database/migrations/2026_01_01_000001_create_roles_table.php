<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfis de acesso (setores). Definem quais menus/rotas cada usuário
 * enxerga. A checagem real de permissão sempre acontece no backend
 * (middleware), nunca apenas escondendo botões no frontend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique(); // admin, compras, financeiro, diretoria...
            $table->string('nome', 80);
            $table->string('descricao', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
