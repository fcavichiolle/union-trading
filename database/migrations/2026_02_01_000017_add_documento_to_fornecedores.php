<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fornecedor passa a aceitar CNPJ **ou** CPF, e o documento vira opcional.
 *
 * Motivo: na planilha da mesa aparecem vendedores "A CONFIRMAR" — exigir
 * CNPJ válido para lançar empurrava a funcionária 2 de volta para o Excel.
 * Sem documento, o fornecedor fica pendente (vira aviso no painel) e a
 * compra pode ser lançada assim mesmo.
 *
 * `documento` guarda só os dígitos (11 = CPF, 14 = CNPJ). Continua único,
 * mas como é nullable vários fornecedores pendentes convivem — nesse caso
 * o reaproveitamento passa a ser pelo NOME (ver Fornecedor::localizarOuCriar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->string('documento', 14)->nullable()->after('nome');
            $table->string('tipo_documento', 4)->nullable()->after('documento'); // CNPJ | CPF
        });

        foreach (DB::table('fornecedores')->get() as $f) {
            $digitos = preg_replace('/\D/', '', (string) $f->cnpj);
            DB::table('fornecedores')->where('id', $f->id)->update([
                'documento' => $digitos !== '' ? $digitos : null,
                'tipo_documento' => strlen($digitos) === 11 ? 'CPF' : (strlen($digitos) === 14 ? 'CNPJ' : null),
            ]);
        }

        Schema::table('fornecedores', function (Blueprint $table) {
            $table->dropUnique(['cnpj']);
            $table->dropColumn('cnpj');
            $table->unique('documento');
        });
    }

    public function down(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->string('cnpj', 14)->nullable();
        });

        DB::table('fornecedores')->update(['cnpj' => DB::raw('documento')]);

        Schema::table('fornecedores', function (Blueprint $table) {
            $table->dropUnique(['documento']);
            $table->dropColumn(['documento', 'tipo_documento']);
        });
    }
};
