<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cancelamento de contrato — diferente de exclusão:
 *
 *  - CANCELAR: o contrato existiu e o cliente desistiu. O registro fica,
 *    marcado com data, autor e o MOTIVO (obrigatório), e sai da posição
 *    (Tela NY, números do painel). É o caso comum.
 *  - EXCLUIR: o contrato foi lançado errado e não deveria existir. Some
 *    do banco (ver ContratoController::destroy, que bloqueia a exclusão
 *    quando já existem fixações registradas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->timestamp('cancelado_em')->nullable()->after('remarks');
            $table->text('motivo_cancelamento')->nullable()->after('cancelado_em');
            $table->foreignId('cancelado_por')->nullable()->after('motivo_cancelamento')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelado_por');
            $table->dropColumn(['cancelado_em', 'motivo_cancelamento']);
        });
    }
};
