<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Compra extends Model
{
    use HasFactory;

    protected $fillable = [
        'uts',
        'mes_ano',
        'fornecedor_id',
        'armazem',
        'certificacao',
        'tipo_entrada',
        'volume_sacas',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'mes_ano' => 'date',
            'volume_sacas' => 'decimal:2',
        ];
    }

    // Regra do enunciado: toda nova compra assume "BICA" como tipo de entrada.
    protected $attributes = [
        'tipo_entrada' => 'BICA',
    ];

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function classificacao(): HasOne
    {
        return $this->hasOne(Classificacao::class);
    }

    public function financeiro(): HasOne
    {
        return $this->hasOne(FinanceiroCompra::class);
    }

    public static function armazens(): array
    {
        return ['SAAG' => 'SAAG', 'QUALITE' => 'QUALITÉ', 'DINAMO_MACHADO' => 'DÍNAMO MACHADO'];
    }

    public static function certificacoes(): array
    {
        return [
            'SEM_CERT' => 'Sem certificação',
            '4C' => '4C',
            'RFA' => 'RFA',
            'EUDR' => 'EUDR',
            '4C_EUDR' => '4C + EUDR',
            'RFA_EUDR' => 'RFA + EUDR',
            '4C_RFA' => '4C + RFA',
        ];
    }
}
