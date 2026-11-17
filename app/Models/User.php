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
        // Bloco de assinatura usado no e-mail de instrução de AA.
        'cargo',
        'cargo_en',
        'telefone',
        'celular',
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

    /**
     * Bloco de assinatura do e-mail de instrução de AA, em texto puro.
     *
     * Na planilha essa assinatura estava escrita DENTRO da macro, então
     * qualquer pessoa que apertasse o botão mandava o e-mail assinado com o
     * nome de quem montou a planilha. Aqui ela é do usuário logado, e cada
     * linha só aparece se estiver preenchida — usuário sem cargo/telefone
     * cadastrado assina só com nome, e-mail e site.
     */
    public function assinaturaEmail(): string
    {
        $linhas = ['Atenciosamente,', '', $this->name];

        foreach ([$this->cargo, $this->cargo_en] as $cargo) {
            if (filled($cargo)) {
                $linhas[] = $cargo;
            }
        }

        $linhas[] = '';

        foreach ([$this->telefone, $this->celular] as $fone) {
            if (filled($fone)) {
                $linhas[] = $fone;
            }
        }

        $linhas[] = $this->email;
        $linhas[] = 'www.utrading.com.br';

        return implode("
", $linhas);
    }
}
