<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Um lote (ou guia de serviço) do estoque do Digisul, dentro de uma foto. */
class FotoEstoqueLote extends Model
{
    protected $table = 'foto_estoque_lotes';

    public $timestamps = false;

    protected $fillable = ['foto_estoque_id', 'lote', 'armazem', 'padrao', 'balde', 'peneira', 'bebida', 'kg', 'sacas'];

    protected function casts(): array
    {
        return ['kg' => 'decimal:2', 'sacas' => 'decimal:2'];
    }

    public function foto(): BelongsTo
    {
        return $this->belongsTo(FotoEstoque::class, 'foto_estoque_id');
    }
}
