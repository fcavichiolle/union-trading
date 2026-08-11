<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma entrada física de café no armazém, referente a uma compra (UTS).
 *
 * A mesma UTS pode ter várias entregas — em meses diferentes, em armazéns
 * diferentes — e cada uma tem o SEU número de lote. O volume aqui é o que
 * o armazém confirmou que entrou, que pode ficar acima ou abaixo do
 * contratado na compra.
 */
class Entrega extends Model
{
    protected $table = 'entregas';

    protected $fillable = [
        'compra_id', 'mes_ano', 'armazem', 'volume_sacas', 'numero_lote', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'mes_ano' => 'date',
            'volume_sacas' => 'decimal:2',
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

    /**
     * Sem o número do lote (dado pelo armazém), a entrega não pode ser
     * considerada definitivamente em estoque.
     */
    public function precisaDeNumeroLote(): bool
    {
        return blank($this->numero_lote);
    }

    public function armazemLabel(): string
    {
        return Compra::armazens()[$this->armazem] ?? $this->armazem;
    }

    /* ---------- Scopes ---------- */

    public function scopeSemNumeroLote(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('numero_lote')->orWhere('numero_lote', ''));
    }

    public function scopeComNumeroLote(Builder $query): Builder
    {
        return $query->whereNotNull('numero_lote')->where('numero_lote', '!=', '');
    }
}
