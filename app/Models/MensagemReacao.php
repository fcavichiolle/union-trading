<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reação de uma pessoa a uma mensagem do canal (ver a migration). */
class MensagemReacao extends Model
{
    protected $table = 'mensagem_reacoes';

    protected $fillable = ['mensagem_id', 'user_id', 'emoji'];

    public function mensagem(): BelongsTo
    {
        return $this->belongsTo(Mensagem::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
