<?php

namespace App\Models;

use App\Rules\ListaDeEmails;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Corretora do cadastro do admin. `tipo` separa as NOSSAS (corretoras da
 * Union, campo "Corretora" da fixação) dos brokers dos CLIENTES (campo
 * opcional "Broker do cliente"). As fixações gravam o NOME como snapshot,
 * então mexer no cadastro não altera fixações já registradas.
 */
class Corretora extends Model
{
    protected $table = 'corretoras';

    protected $fillable = ['nome', 'tipo', 'emails_para', 'emails_copia'];

    /** @return array<string,string> tipo => rótulo */
    public static function tipos(): array
    {
        return ['NOSSA' => 'Nossa corretora', 'CLIENTE' => 'Broker do cliente'];
    }

    public function scopeNossas(Builder $query): Builder
    {
        return $query->where('tipo', 'NOSSA');
    }

    public function scopeDoCliente(Builder $query): Builder
    {
        return $query->where('tipo', 'CLIENTE');
    }

    /**
     * Destinatários do e-mail de instrução de AA (aba EMAILS da planilha).
     * Quem recebe é definido pela NOSSA corretora — é ela que executa o AA.
     *
     * @return array<int, string>
     */
    public function emailsPara(): array
    {
        return ListaDeEmails::separar($this->emails_para);
    }

    /** @return array<int, string> */
    public function emailsCopia(): array
    {
        return ListaDeEmails::separar($this->emails_copia);
    }

    /**
     * Acha a corretora pelo NOME gravado na fixação. A fixação guarda o nome
     * como snapshot, então o cadastro pode ter sido renomeado ou excluído
     * depois — daí o retorno nulo, tratado como "sem e-mails cadastrados".
     */
    public static function porNome(?string $nome, string $tipo): ?self
    {
        if ($nome === null || $nome === '') {
            return null;
        }

        return static::where('tipo', $tipo)->where('nome', $nome)->first();
    }
}
