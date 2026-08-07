<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contratos de exportação (identificados por "UT <numero>").
 *
 * Guardamos SNAPSHOTS do que é mutável (nome/endereço do cliente e a
 * descrição da qualidade) e os valores CALCULADOS (sacas, lotes,
 * containers, peso por container) no momento da geração — assim o PDF de
 * um contrato antigo nunca muda se o cadastro do cliente/qualidade for
 * editado depois. As cláusulas fixas (SELLER, PAYMENT, etc.) vivem no
 * template, não no banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_ut', 40)->unique()->comment('Número do contrato UT (digitado, único)');
            $table->date('data_contrato');

            // BUYER (com snapshot do nome/endereço no momento da criação)
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_nome', 150);
            $table->text('cliente_endereco');
            $table->string('buyer_ref', 80)->nullable();

            // Produto
            $table->foreignId('qualidade_id')->nullable()->constrained('qualidades')->nullOnDelete();
            $table->string('qualidade_descricao', 200);
            $table->enum('tipo_cafe', ['ARABICA', 'CONILON']);
            $table->string('certificado', 40);
            $table->decimal('quantidade_kg', 12, 2);
            $table->enum('tipo_container', ['20', '40']);
            $table->string('embalagem', 60);

            // Valores calculados (snapshot)
            $table->unsignedInteger('containers');
            $table->decimal('kg_por_container', 12, 2);
            $table->decimal('sacas', 12, 2);
            $table->unsignedInteger('lotes');

            // Preço e logística
            $table->string('diferencial', 40)->nullable();      // ex.: "-16.00"
            $table->string('mes_fixacao', 10)->nullable();       // ex.: "Z6"
            $table->date('embarque_mes')->nullable();            // 1º dia do mês de embarque
            $table->string('incoterms', 10);                     // ex.: "FOB"
            $table->enum('porto', ['SANTOS', 'VITORIA']);
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('data_contrato');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
