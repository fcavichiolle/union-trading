<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fornecedores', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 180);
            $table->string('cnpj', 18)->unique(); // formato 00.000.000/0000-00, validado na FormRequest
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornecedores');
    }
};
