<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceiroCompra extends Model
{
    use HasFactory;

    protected $table = 'financeiro_compras';

    protected $fillable = [
        'compra_id',
        'valor_saca',
        'corretor_nome',
        'comissao_pct',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valor_saca' => 'decimal:2',
            'valor_total' => 'decimal:2',
            'comissao_pct' => 'decimal:2',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        // valor_total é SEMPRE recalculado no servidor (valor_saca * volume
        // da compra). O formulário nunca envia valor_total diretamente,
        // evitando que alguém manipule o total via requisição manual.
        static::saving(function (FinanceiroCompra $financeiro) {
            $volume = (float) ($financeiro->compra?->volume_sacas
                ?? Compra::find($financeiro->compra_id)?->volume_sacas
                ?? 0);

            $financeiro->valor_total = round((float) $financeiro->valor_saca * $volume, 2);
        });
    }
}
