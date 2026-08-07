<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplia o ENUM de `classificacoes.padrao_final` para incluir os novos
 * padrões (GOOD_CUP_2R e RIO_MINAS). Só faz sentido no MySQL — no SQLite
 * (usado nos testes) a coluna é apenas texto, então nada precisa mudar.
 *
 * Espelha a lista de App\Models\Classificacao::padroes().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE classificacoes MODIFY padrao_final ENUM('FINE_CUP','GOOD_CUP','GOOD_CUP_2R','RIO_MINAS') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE classificacoes MODIFY padrao_final ENUM('FINE_CUP','GOOD_CUP') NOT NULL");
    }
};
