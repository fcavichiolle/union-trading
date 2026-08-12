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
        'compra_id', 'data_entrega', 'armazem_id', 'volume_sacas', 'peso_kg', 'numero_lote', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            // Data completa (dia/mês/ano): a auditoria precisa saber o DIA
            // em que o café entrou, não só o mês.
            'data_entrega' => 'date',
            'volume_sacas' => 'decimal:2',
            'peso_kg' => 'decimal:2',
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

    public function armazem(): BelongsTo
    {
        return $this->belongsTo(Armazem::class);
    }

    /**
     * Sem o número do lote (dado pelo armazém), a entrega não pode ser
     * considerada definitivamente em estoque.
     */
    public function precisaDeNumeroLote(): bool
    {
        return blank($this->numero_lote);
    }

    /**
     * Nome do armazém. Usa a relação quando já carregada e cai na lista
     * memorizada de Armazem::lista() quando não — evita uma query por linha
     * nas telas que listam muitas entregas.
     */
    public function armazemLabel(): string
    {
        if ($this->relationLoaded('armazem') && $this->armazem) {
            return $this->armazem->nome;
        }

        return Armazem::nomeDe($this->armazem_id) ?? '—';
    }

    /**
     * Peso que o armazém informou; quando só vieram as sacas, o
     * equivalente em quilos (60 kg/saca).
     */
    public function pesoOuEquivalente(): float
    {
        return $this->peso_kg !== null
            ? (float) $this->peso_kg
            : Compra::pesoDeSacas((float) $this->volume_sacas);
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
