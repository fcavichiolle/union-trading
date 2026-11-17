<?php

namespace App\Models;

use App\Rules\ListaDeEmails;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = ['nome', 'endereco', 'ref_padrao', 'emails_copia'];

    /**
     * E-mails do cliente que entram em CÓPIA na instrução de AA — soma-se
     * aos da nossa corretora (era a coluna "e-mails em cópia" da aba
     * CLIENTES da planilha).
     *
     * @return array<int, string>
     */
    public function emailsCopia(): array
    {
        return ListaDeEmails::separar($this->emails_copia);
    }
}
