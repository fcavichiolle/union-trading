<?php

namespace App\Models;

use App\Rules\ListaDeEmails;
use Illuminate\Database\Eloquent\Model;

/**
 * Transportadora do cadastro. Serve para duas coisas: sugerir o nome no
 * formulário do embarque e dar os destinatários do e-mail de programação.
 * O embarque guarda o NOME como texto, então mexer aqui não altera o
 * histórico (mesma lógica da corretora na fixação).
 */
class Transportadora extends Model
{
    protected $table = 'transportadoras';

    protected $fillable = ['nome', 'emails'];

    /** @return array<int, string> */
    public function listaDeEmails(): array
    {
        return ListaDeEmails::separar($this->emails);
    }

    /** Acha pelo nome gravado no embarque, sem diferenciar maiúsculas. */
    public static function porNome(?string $nome): ?self
    {
        if ($nome === null || trim($nome) === '') {
            return null;
        }

        // Comparação em PHP, não em SQL: o LOWER() do SQLite só converte
        // ASCII ("TRANSAÇUCENA" não casaria com "transaçucena"), e o
        // cadastro é pequeno o bastante para carregar inteiro.
        $alvo = mb_strtolower(trim($nome));

        return static::all()->first(fn (self $t) => mb_strtolower($t->nome) === $alvo);
    }
}
