<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastro de corretoras gerenciado pelo admin (antes eram listas fixas em
 * Fixacao::corretoras()/brokersCliente()). Dois tipos na mesma tabela:
 * NOSSA (corretoras da Union) e CLIENTE (brokers usados pelos compradores).
 *
 * As fixações passam a gravar o NOME da corretora (snapshot), não um
 * código/FK — renomear ou excluir um cadastro não altera fixações antigas.
 * Esta migration também converte os códigos já gravados em `fixacoes`
 * para os nomes correspondentes.
 */
return new class extends Migration
{
    /** Listas que estavam no código; viram as linhas iniciais do cadastro. */
    private const NOSSAS = [
        'STONEX' => 'StoneX East Coast',
        'ICAP' => 'ICAP Corporates LLC (Hedgepoint)',
        'MAREX_AGS' => 'Marex Financial Limited AGS Coffee',
    ];

    private const CLIENTES = [
        'STONEX_MIAMI' => 'Stonex Miami',
        'ADMIS' => 'Adm Investor Services Inc',
        'MACQUARIE_USA' => 'Macquarie USA',
        'STONEX_LONDON' => 'Stonex London',
        'SUCDEN_LONDON' => 'Sucden London',
        'MACQUARIE_FUTURES' => 'Macquarie futures broker LLC',
        'STONEX_EAST_COAST' => 'Stonex East Coast',
        'MAREX_LONDON' => 'Marex London',
    ];

    public function up(): void
    {
        Schema::create('corretoras', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 80);
            $table->string('tipo', 10); // NOSSA | CLIENTE (validado na aplicação)
            $table->timestamps();
            $table->unique(['nome', 'tipo']);
        });

        $agora = now();
        foreach (self::NOSSAS as $nome) {
            DB::table('corretoras')->insert(['nome' => $nome, 'tipo' => 'NOSSA', 'created_at' => $agora, 'updated_at' => $agora]);
        }
        foreach (self::CLIENTES as $nome) {
            DB::table('corretoras')->insert(['nome' => $nome, 'tipo' => 'CLIENTE', 'created_at' => $agora, 'updated_at' => $agora]);
        }

        // Fixações antigas guardavam o código ('STONEX'); passam a guardar o nome.
        foreach (self::NOSSAS as $codigo => $nome) {
            DB::table('fixacoes')->where('corretora', $codigo)->update(['corretora' => $nome]);
        }
        foreach (self::CLIENTES as $codigo => $nome) {
            DB::table('fixacoes')->where('broker_cliente', $codigo)->update(['broker_cliente' => $nome]);
        }
    }

    public function down(): void
    {
        // Volta os nomes para os códigos antigos antes de derrubar a tabela.
        foreach (self::NOSSAS as $codigo => $nome) {
            DB::table('fixacoes')->where('corretora', $nome)->update(['corretora' => $codigo]);
        }
        foreach (self::CLIENTES as $codigo => $nome) {
            DB::table('fixacoes')->where('broker_cliente', $nome)->update(['broker_cliente' => $codigo]);
        }

        Schema::drop('corretoras');
    }
};
