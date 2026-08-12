<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Armazéns deixam de ser lista fixa no código e passam a ser CADASTRO.
 *
 * Antes eram um ENUM com três valores (SAAG, QUALITE, DINAMO_MACHADO) em
 * `entregas.armazem` — cada armazém novo exigia migration de ALTER. Como a
 * mesa passa a usar outros armazéns, viraram tabela, com cidade, estado,
 * endereço e CNPJ (este opcional, igual ao do fornecedor: o dado chega
 * depois e não vale travar o cadastro por causa dele).
 *
 * A entrega passa a apontar para o cadastro (`armazem_id`), não para um
 * código solto: assim renomear um armazém não parte o histórico em dois
 * grupos no Estoque — é o mesmo lugar físico, com nome novo.
 *
 * A COMPRA também ganha o armazém, como PREVISTO (nullable): é o destino
 * combinado no negócio e serve para já vir escolhido na hora de lançar a
 * entrega. Quem manda no estoque continua sendo a entrega — o café pode
 * chegar em outro armazém, e isso não é erro.
 */
return new class extends Migration
{
    /** Os três que existiam no ENUM, com os dados que sabemos. */
    private const INICIAIS = [
        'SAAG' => ['nome' => 'SAAG', 'cidade' => 'Santos', 'estado' => 'SP'],
        'QUALITE' => ['nome' => 'QUALITÉ', 'cidade' => 'Santos', 'estado' => 'SP'],
        'DINAMO_MACHADO' => ['nome' => 'DÍNAMO MACHADO', 'cidade' => 'Machado', 'estado' => 'MG'],
    ];

    public function up(): void
    {
        Schema::create('armazens', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120)->unique();
            $table->string('cidade', 120);
            $table->string('estado', 2);
            $table->string('endereco', 200)->nullable();
            // Só dígitos, como em fornecedores. Opcional de propósito.
            $table->string('documento', 14)->nullable();
            $table->timestamps();
        });

        $ids = [];

        foreach (self::INICIAIS as $codigo => $dados) {
            $ids[$codigo] = DB::table('armazens')->insertGetId($dados + [
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('entregas', function (Blueprint $table) {
            $table->foreignId('armazem_id')->nullable()->after('data_entrega')
                ->constrained('armazens')->restrictOnDelete();
        });

        Schema::table('compras', function (Blueprint $table) {
            // Armazém previsto no negócio; a entrega pode divergir.
            $table->foreignId('armazem_id')->nullable()->after('logistica')
                ->constrained('armazens')->nullOnDelete();
        });

        foreach ($ids as $codigo => $id) {
            DB::table('entregas')->where('armazem', $codigo)->update(['armazem_id' => $id]);
        }

        Schema::table('entregas', function (Blueprint $table) {
            $table->dropColumn('armazem');
        });
    }

    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->enum('armazem', array_keys(self::INICIAIS))->nullable();
        });

        foreach (self::INICIAIS as $codigo => $dados) {
            $id = DB::table('armazens')->where('nome', $dados['nome'])->value('id');

            if ($id) {
                DB::table('entregas')->where('armazem_id', $id)->update(['armazem' => $codigo]);
            }
        }

        Schema::table('entregas', function (Blueprint $table) {
            $table->dropForeign(['armazem_id']);
            $table->dropColumn('armazem_id');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->dropForeign(['armazem_id']);
            $table->dropColumn('armazem_id');
        });

        Schema::dropIfExists('armazens');
    }
};
