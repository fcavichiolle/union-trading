<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'force_password_change',
        'active',
    ];

    // NUNCA remover 'password'/'remember_token' daqui: impede que apareçam
    // em respostas JSON/serialização acidental (ex.: debugbar, API futura).
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            // Marca de leitura do canal de mensagens (ver Mensagem::scopeNaoLidasPor).
            'mensagens_lidas_em' => 'datetime',
            'force_password_change' => 'boolean',
            'active' => 'boolean',
            // Laravel faz o hash automaticamente ao atribuir texto puro a este
            // campo (Hash::make embutido) e nunca expõe o valor em array/JSON.
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Confere se o usuário tem um dos perfis informados. Ex: $user->hasRole('admin','compras') */
    public function hasRole(string ...$slugs): bool
    {
        return in_array($this->role?->slug, $slugs, true);
    }

    /**
     * Sobrescreve o comportamento padrão de "pode autenticar": bloqueia
     * login de contas desativadas mesmo que a senha esteja correta.
     */
    public function canAuthenticate(): bool
    {
        return $this->active === true;
    }
}
