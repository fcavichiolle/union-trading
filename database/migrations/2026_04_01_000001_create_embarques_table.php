<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Embarques (nov/2026) — primeiro passo para tirar a posição da planilha.
 *
 * Até aqui a logística de cada venda vivia em dois lugares fora do sistema:
 * a aba VENDAS-FOB da planilha de posição (mês de faturamento, status,
 * armazém, navio, booking) e uma planilha do Google com a agenda das
 * estufagens (PSS, fumigação, deadlines, transportadora). As duas repetiam
 * o contrato, digitado de novo.
 *
 * `embarques` é a PARCELA do contrato que sai num container/lote de
 * containers. A maioria dos contratos tem um embarque só; alguns são
 * divididos (UT 5973 A, B, C e D), cada parcela com a sua estufagem.
 * O mês de faturamento é da parcela, não do contrato, porque uma parcela
 * pode rolar para o mês seguinte sem as outras.
 *
 * `contratos.standard` é o BALDE da posição (17/18 FC, 14/16 GC, grinders…),
 * que não é a descrição comercial da qualidade: a posição Long & Short
 * soma vendas e estoque por esse balde, e sem ele a venda não tem linha
 * onde entrar. Fica nulo para contrato que não entra na posição.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('standard', 20)->nullable()->after('qualidade_descricao');
        });

        Schema::create('embarques', function (Blueprint $table) {
            $table->id();
            // Cascata como nas fixações — e, como lá, o controller BLOQUEIA
            // excluir contrato que tem embarque, senão a logística iria junto.
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->string('sufixo', 5)->nullable();        // "A", "B"… quando o contrato é dividido
            $table->date('mes_faturamento');                // sempre dia 01
            $table->decimal('quantidade_kg', 12, 2);
            $table->decimal('sacas', 12, 2);                // calculado no servidor (kg ÷ kg por saca do contrato)
            $table->string('status', 20)->default('ABERTO');
            $table->string('pss', 15)->nullable();          // amostra pré-embarque
            $table->string('pss_obs', 120)->nullable();
            // Armazém de onde sai. Excluir armazém em uso é bloqueado no
            // cadastro; o nullOnDelete é só a rede de segurança.
            $table->foreignId('armazem_id')->nullable()->constrained('armazens')->nullOnDelete();
            $table->string('transportadora', 60)->nullable();
            $table->date('data_fumigacao')->nullable();
            $table->date('data_estufagem')->nullable();
            $table->date('deadline_documentos')->nullable();
            $table->date('deadline_carga')->nullable();
            $table->date('data_fitossanitario')->nullable();
            $table->string('destino', 80)->nullable();
            $table->date('etd')->nullable();
            $table->string('navio', 80)->nullable();
            $table->string('booking', 120)->nullable();
            $table->string('pilha', 80)->nullable();        // lote preparado (ex.: PI9074/26) — liga ao estoque
            $table->date('faturado_em')->nullable();        // data da nota; é ela que tira a venda da posição
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['mes_faturamento', 'status']);
            $table->index('data_estufagem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embarques');

        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('standard');
        });
    }
};
