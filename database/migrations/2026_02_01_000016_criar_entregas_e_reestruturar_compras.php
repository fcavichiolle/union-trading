<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separa COMPRA (o negócio) de ENTREGA (cada entrada física no armazém).
 *
 * Antes, uma compra era uma linha só: um armazém, um volume, um número de
 * lote — e `uts` é única. Isso tornava impossível registrar a realidade da
 * mesa: a mesma UTS pode ser entregue em partes, em meses diferentes e em
 * armazéns diferentes, cada parte com o seu próprio número de lote.
 *
 * Agora:
 *  - `compras` guarda o que foi NEGOCIADO (funcionário 1 → funcionária 2):
 *    data, fornecedor, volume contratado, preço, corretor, pagamento,
 *    logística e certificação;
 *  - `entregas` guarda o que REALMENTE ENTROU (funcionário 3): mês,
 *    armazém, volume efetivo e número do lote.
 *
 * O volume da entrega pode ficar acima ou abaixo do contratado — quem
 * confere é o armazém. O sistema mostra a diferença em vez de impedir.
 *
 * O financeiro deixou de ter tabela própria: preço, corretor e comissão
 * são dados da negociação e passam a viver na própria compra (a tela do
 * perfil financeiro continua existindo, editando essas colunas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->date('mes_ano')->comment('Mês/ano da entrada no armazém (dia 01)');
            $table->enum('armazem', ['SAAG', 'QUALITE', 'DINAMO_MACHADO']);
            $table->decimal('volume_sacas', 12, 2)->comment('Sacas que realmente entraram');
            $table->string('numero_lote', 60)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Cada compra vira uma entrega com o que já estava lançado, para não
        // perder nada do que existir no banco no momento da migração.
        foreach (DB::table('compras')->get() as $compra) {
            DB::table('entregas')->insert([
                'compra_id' => $compra->id,
                'mes_ano' => $compra->mes_ano,
                'armazem' => $compra->armazem,
                'volume_sacas' => $compra->volume_sacas,
                'numero_lote' => $compra->numero_lote,
                'created_by' => $compra->created_by,
                'created_at' => $compra->created_at,
                'updated_at' => $compra->updated_at,
            ]);
        }

        Schema::table('compras', function (Blueprint $table) {
            $table->date('data_compra')->nullable()->after('uts');
            $table->string('logistica', 10)->nullable()->after('certificacao');
            $table->decimal('valor_saca', 12, 2)->nullable()->after('logistica');
            $table->string('corretor_nome', 150)->nullable()->after('valor_saca');
            $table->decimal('comissao_pct', 5, 2)->nullable()->after('corretor_nome');
            $table->date('pagamento_previsto')->nullable()->after('comissao_pct');
            $table->string('pagamento_obs', 200)->nullable()->after('pagamento_previsto');
            // `volume_sacas` passa a ser o CONTRATADO; o realizado mora em
            // entregas.volume_sacas. Nome novo para não confundir os dois.
            $table->renameColumn('volume_sacas', 'volume_contratado');
        });

        // data_compra herda o mês/ano antigo; preço/corretor vêm do financeiro.
        DB::table('compras')->update(['data_compra' => DB::raw('mes_ano')]);

        if (Schema::hasTable('financeiro_compras')) {
            foreach (DB::table('financeiro_compras')->get() as $fin) {
                DB::table('compras')->where('id', $fin->compra_id)->update([
                    'valor_saca' => $fin->valor_saca,
                    'corretor_nome' => $fin->corretor_nome,
                    'comissao_pct' => $fin->comissao_pct,
                ]);
            }
            Schema::drop('financeiro_compras');
        }

        Schema::table('compras', function (Blueprint $table) {
            // O índice de mes_ano precisa cair ANTES da coluna: no SQLite,
            // remover uma coluna indexada sem soltar o índice quebra a
            // migration ("error in index ... after drop column").
            $table->dropIndex(['mes_ano']);
        });

        Schema::table('compras', function (Blueprint $table) {
            // Armazém, mês e lote agora pertencem à entrega.
            $table->dropColumn(['mes_ano', 'armazem', 'numero_lote']);
            // O recorte por período passa a ser pela data do negócio.
            $table->index('data_compra');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropIndex(['data_compra']);
            $table->date('mes_ano')->nullable();
            $table->enum('armazem', ['SAAG', 'QUALITE', 'DINAMO_MACHADO'])->nullable();
            $table->string('numero_lote', 60)->nullable();
            $table->renameColumn('volume_contratado', 'volume_sacas');
        });

        // Devolve a primeira entrega de cada compra para a própria compra.
        foreach (DB::table('entregas')->orderBy('id')->get()->groupBy('compra_id') as $compraId => $entregas) {
            $primeira = $entregas->first();
            DB::table('compras')->where('id', $compraId)->update([
                'mes_ano' => $primeira->mes_ano,
                'armazem' => $primeira->armazem,
                'numero_lote' => $primeira->numero_lote,
            ]);
        }

        Schema::create('financeiro_compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->unique()->constrained('compras')->cascadeOnDelete();
            $table->decimal('valor_saca', 12, 2);
            $table->decimal('valor_total', 14, 2)->default(0);
            $table->string('corretor_nome', 150)->nullable();
            $table->decimal('comissao_pct', 5, 2)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::drop('entregas');

        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn([
                'data_compra', 'logistica', 'valor_saca', 'corretor_nome',
                'comissao_pct', 'pagamento_previsto', 'pagamento_obs',
            ]);
        });
    }
};
