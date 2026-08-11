<?php

namespace App\Models;

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

    protected $fillable = ['nome', 'tipo'];

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
}
