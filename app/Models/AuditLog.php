<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false; // só usamos created_at (useCurrent no banco)

    protected $fillable = ['user_id', 'acao', 'descricao', 'ip_address'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Helper central para registrar eventos sensíveis de segurança. */
    public static function registrar(string $acao, ?string $descricao = null, ?int $userId = null): void
    {
        static::create([
            'user_id' => $userId ?? auth()->id(),
            'acao' => $acao,
            'descricao' => $descricao,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
