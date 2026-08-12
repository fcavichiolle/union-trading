<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma mensagem no canal geral da equipe (mural interno).
 *
 * O texto é sempre escapado na exibição (Blade por padrão, e `textContent`
 * no JS que insere as mensagens novas): é conteúdo escrito por usuário e
 * não pode virar HTML na tela de ninguém.
 */
class Mensagem extends Model
{
    // Sem isto o Laravel procuraria "mensagems" (GOTCHA 2 do PROGRESSO: a
    // pluralização é feita em inglês).
    protected $table = 'mensagens';

    protected $fillable = ['user_id', 'texto'];

    /** Quantas mensagens a tela carrega de uma vez. */
    public const POR_PAGINA = 50;

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Mensagens depois de um id — é o que o polling da tela pede. */
    public function scopeDepoisDe(Builder $query, int $id): Builder
    {
        return $query->where('id', '>', $id);
    }

    /**
     * Não lidas de um usuário: mensagens de OUTRAS pessoas criadas depois da
     * última vez que ele abriu o canal. A própria mensagem nunca conta como
     * não lida — ninguém precisa ser avisado do que acabou de escrever.
     */
    public function scopeNaoLidasPor(Builder $query, User $user): Builder
    {
        return $query->where('user_id', '!=', $user->id)
            ->when(
                $user->mensagens_lidas_em !== null,
                fn (Builder $q) => $q->where('created_at', '>', $user->mensagens_lidas_em)
            );
    }

    /** Quem pode apagar: o autor, ou um admin (que responde pelo mural). */
    public function podeSerApagadaPor(User $user): bool
    {
        return $this->user_id === $user->id || $user->hasRole('admin');
    }

    /** Iniciais para o avatar, no mesmo formato do header. */
    public function iniciaisDoAutor(): string
    {
        $nome = trim((string) $this->autor?->name);

        if ($nome === '') {
            return '??';
        }

        $partes = collect(preg_split('/\s+/', $nome))->filter()->values();

        return $partes->count() > 1
            ? mb_strtoupper(mb_substr($partes->first(), 0, 1) . mb_substr($partes->last(), 0, 1))
            : mb_strtoupper(mb_substr($nome, 0, 2));
    }

    /**
     * Dados que a tela precisa, no formato que o JS do polling consome.
     *
     * @return array<string,mixed>
     */
    public function paraTela(User $usuarioAtual): array
    {
        return [
            'id' => $this->id,
            'texto' => $this->texto,
            'autor' => $this->autor?->name ?? 'Usuário removido',
            'iniciais' => $this->iniciaisDoAutor(),
            'minha' => $this->user_id === $usuarioAtual->id,
            'hora' => $this->created_at->format('H:i'),
            'dia' => $this->created_at->format('d/m/Y'),
            'pode_apagar' => $this->podeSerApagadaPor($usuarioAtual),
        ];
    }
}
