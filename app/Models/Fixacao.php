<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fixacao extends Model
{
    // Sem isso o Eloquent procuraria a tabela "fixacaos" (pluralização inglesa).
    protected $table = 'fixacoes';

    protected $fillable = [
        'contrato_id', 'corretora', 'broker_cliente', 'tela', 'lotes', 'level', 'diferencial', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'lotes' => 'integer',
            'level' => 'decimal:2',
            'diferencial' => 'decimal:2',
            'preco' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Preço da tranche SEMPRE recalculado no servidor a partir de
        // level + diferencial — nunca aceito do formulário.
        static::saving(function (Fixacao $f) {
            $f->preco = round((float) $f->level + (float) $f->diferencial, 2);
        });
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // As listas de corretoras/brokers deixaram de ser fixas no código:
    // moraram no cadastro do admin (model Corretora, /admin/corretoras).
    // A fixação grava o NOME como snapshot — `corretora` e `broker_cliente`
    // são strings legíveis e não mudam se o cadastro for editado depois.
}
