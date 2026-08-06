<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Classificacao extends Model
{
    use HasFactory;

    protected $table = 'classificacoes';

    /** Divisor fixo do enunciado para cálculo de lotes. */
    public const SACAS_POR_LOTE = 283.49;

    protected $fillable = [
        'compra_id',
        'padrao_final',
        'peneira_1718_pct',
        'peneira_1718_sacas',
        'peneira_1416_pct',
        'peneira_1416_sacas',
        'mercado_interno_pct',
        'mercado_interno_sacas',
        'grinders_pct',
        'grinders_sacas',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'peneira_1718_pct' => 'decimal:2',
            'peneira_1718_sacas' => 'decimal:2',
            'peneira_1416_pct' => 'decimal:2',
            'peneira_1416_sacas' => 'decimal:2',
            'mercado_interno_pct' => 'decimal:2',
            'mercado_interno_sacas' => 'decimal:2',
            'grinders_pct' => 'decimal:2',
            'grinders_sacas' => 'decimal:2',
            'quantidade_lotes' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        // Cálculo automático de lotes: SEMPRE recalculado no servidor a
        // partir da soma das sacas, nunca aceito como valor vindo do form.
        static::saving(function (Classificacao $classificacao) {
            $totalSacas = (float) $classificacao->peneira_1718_sacas
                + (float) $classificacao->peneira_1416_sacas
                + (float) $classificacao->mercado_interno_sacas
                + (float) $classificacao->grinders_sacas;

            $classificacao->quantidade_lotes = round($totalSacas / self::SACAS_POR_LOTE, 4);
        });
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalSacas(): float
    {
        return (float) $this->peneira_1718_sacas
            + (float) $this->peneira_1416_sacas
            + (float) $this->mercado_interno_sacas
            + (float) $this->grinders_sacas;
    }
}