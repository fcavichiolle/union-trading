<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instruções de AA por e-mail (set/2026).
 *
 * O e-mail de AA ("against actuals") era montado numa planilha com macro
 * (`AA instructions v2.xlsm`), que guardava as listas de destinatários em
 * duas abas. Trazendo a instrução para o sistema, essas listas viram
 * cadastro:
 *
 * - `corretoras.emails_para` / `emails_copia` = a aba EMAILS (o destinatário
 *   é definido pela NOSSA corretora — quem executa o AA);
 * - `clientes.emails_copia` = a coluna "e-mails em cópia" da aba CLIENTES;
 * - os campos de assinatura em `users` substituem o bloco que estava
 *   escrito dentro da macro (e que assinava todo mundo com o mesmo nome).
 *
 * `fixacoes.grupo` marca as tranches gravadas no MESMO envio do formulário.
 * Sem isso não haveria como remontar depois o e-mail de uma fixação em
 * grupo: cada contrato viraria uma instrução solta, quando na prática o
 * broker recebe UMA instrução por cliente com todos os contratos dentro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corretoras', function (Blueprint $table) {
            // TEXT, não VARCHAR: a lista da StoneX já tem 9 endereços e
            // passa folgado dos 255 caracteres.
            $table->text('emails_para')->nullable()->after('tipo');
            $table->text('emails_copia')->nullable()->after('emails_para');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->text('emails_copia')->nullable()->after('ref_padrao');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('cargo', 80)->nullable()->after('email');
            $table->string('cargo_en', 80)->nullable()->after('cargo');
            $table->string('telefone', 40)->nullable()->after('cargo_en');
            $table->string('celular', 40)->nullable()->after('telefone');
        });

        Schema::table('fixacoes', function (Blueprint $table) {
            $table->uuid('grupo')->nullable()->after('contrato_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('corretoras', function (Blueprint $table) {
            $table->dropColumn(['emails_para', 'emails_copia']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('emails_copia');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cargo', 'cargo_en', 'telefone', 'celular']);
        });

        Schema::table('fixacoes', function (Blueprint $table) {
            $table->dropIndex(['grupo']);
            $table->dropColumn('grupo');
        });
    }
};
