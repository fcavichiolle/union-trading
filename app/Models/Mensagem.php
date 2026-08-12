<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Uma mensagem no canal geral da equipe (mural interno).
 *
 * TEXTO CIFRADO NO BANCO (cast `encrypted`, AES-256 com a APP_KEY). Protege
 * dump/backup e acesso só ao MySQL — onde dado vaza na prática. NÃO protege
 * quem tem servidor + chave (a tela precisa descriptografar), nem o
 * trânsito, que é papel do HTTPS. Duas consequências assumidas:
 *  - a APP_KEY fica insubstituível: perdê-la é perder as mensagens;
 *  - não existe busca por conteúdo em SQL (não se faz LIKE em cifrado); se
 *    um dia precisar, tem de ser filtro em memória.
 *
 * O texto é sempre escapado na exibição (Blade por padrão, e `textContent`
 * no JS): é conteúdo escrito por usuário e não pode virar HTML na tela.
 */
class Mensagem extends Model
{
    // Sem isto o Laravel procuraria "mensagems" (GOTCHA 2 do PROGRESSO: a
    // pluralização é feita em inglês).
    protected $table = 'mensagens';

    protected $fillable = ['user_id', 'texto'];

    /** Quantas mensagens a tela carrega de uma vez. */
    public const POR_PAGINA = 50;

    protected function casts(): array
    {
        return [
            // Cifrado no banco; em claro só na memória da aplicação.
            'texto' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        // As menções são derivadas do texto, então quem grava o texto grava as
        // menções — no MODEL, não no controller. Deixar isso no controller era
        // convite a mensagem sem menção quando ela nasce por outro caminho
        // (seeder, gerador da demo, import futuro).
        static::saved(function (Mensagem $mensagem) {
            if ($mensagem->wasRecentlyCreated || $mensagem->wasChanged('texto')) {
                $mensagem->registrarMencoes();
            }
        });
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Quem foi mencionado com @nome nesta mensagem.
     *
     * Sem `withTimestamps()`: o pivô tem só `created_at` (não há o que
     * atualizar numa menção), e o Laravel tentaria escrever `updated_at`.
     * O created_at é passado no attach.
     */
    public function mencionados(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mensagem_mencoes');
    }

    /**
     * Grava as menções encontradas no texto. Chamado pelo evento `saved`.
     *
     * A lista de usuários pode ser passada (a tela já tem ela em mãos) ou é
     * buscada aqui — é uma query numa tabela pequena, por mensagem escrita.
     *
     * @param  \Illuminate\Support\Collection<int, User>|null  $usuarios
     */
    public function registrarMencoes($usuarios = null): void
    {
        $usuarios ??= User::where('active', true)->get();

        $ids = self::detectarMencoes($this->texto, $usuarios);

        // Ninguém precisa ser avisado de que citou a si mesmo.
        $ids = array_values(array_diff($ids, [$this->user_id]));

        $this->mencionados()->sync(
            collect($ids)->mapWithKeys(fn (int $id) => [$id => ['created_at' => now()]])->all()
        );
    }

    /* ---------------- menções ---------------- */

    /**
     * Percorre o texto marcando as menções — é a ÚNICA leitura de "@Nome" do
     * sistema: o destaque na tela e o aviso de citação saem os dois daqui.
     * Com duas implementações (uma para destacar, outra para avisar) elas
     * discordavam: "@Ana Paula" destacava a Ana Paula e avisava também a Ana.
     *
     * Compara contra os nomes CADASTRADOS, não com um regex de "@palavra":
     * nome de gente tem espaço, então "@Luiz Henrique" é uma menção só — e
     * "@luiz" (primeiro nome) também acha. O casamento é do nome mais longo
     * para o mais curto e CONSOME o trecho, senão "@Ana" pegaria dentro de
     * "@Ana Paula".
     *
     * @param  \Illuminate\Support\Collection<int, User>  $usuarios
     * @return array<int, array{texto: string, user_id: int|null}>
     */
    private static function analisar(string $texto, $usuarios): array
    {
        $alvos = [];

        foreach ($usuarios as $usuario) {
            $nome = trim((string) $usuario->name);

            if ($nome === '') {
                continue;
            }

            foreach (array_unique([$nome, explode(' ', $nome)[0]]) as $alvo) {
                $alvos[] = ['texto' => $alvo, 'id' => $usuario->id];
            }
        }

        usort($alvos, fn ($a, $b) => mb_strlen($b['texto']) <=> mb_strlen($a['texto']));

        $pedacos = [];
        $buffer = '';
        $i = 0;
        $tamanho = mb_strlen($texto);

        while ($i < $tamanho) {
            $achou = null;

            if (mb_substr($texto, $i, 1) === '@') {
                foreach ($alvos as $alvo) {
                    $trecho = mb_substr($texto, $i + 1, mb_strlen($alvo['texto']));

                    if (mb_strtolower($trecho) === mb_strtolower($alvo['texto'])) {
                        $achou = $alvo;
                        break;
                    }
                }
            }

            if ($achou === null) {
                $buffer .= mb_substr($texto, $i, 1);
                $i++;

                continue;
            }

            if ($buffer !== '') {
                $pedacos[] = ['texto' => $buffer, 'user_id' => null];
                $buffer = '';
            }

            $pedacos[] = ['texto' => '@' . $achou['texto'], 'user_id' => $achou['id']];
            $i += 1 + mb_strlen($achou['texto']);
        }

        if ($buffer !== '') {
            $pedacos[] = ['texto' => $buffer, 'user_id' => null];
        }

        return $pedacos;
    }

    /**
     * Ids dos usuários citados no texto.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $usuarios
     * @return array<int, int>
     */
    public static function detectarMencoes(string $texto, $usuarios): array
    {
        return collect(self::analisar($texto, $usuarios))
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Pedaços para a tela destacar as menções sem montar HTML com texto de
     * usuário: cada pedaço é ['texto', 'mencao', 'para_mim'] e a view/JS
     * escreve o texto como TEXTO.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $usuarios
     * @return array<int, array<string, mixed>>
     */
    public static function segmentos(string $texto, $usuarios, ?int $usuarioAtualId = null): array
    {
        return array_map(fn (array $p) => [
            'texto' => $p['texto'],
            'mencao' => $p['user_id'] !== null,
            'para_mim' => $p['user_id'] !== null && $p['user_id'] === $usuarioAtualId,
        ], self::analisar($texto, $usuarios));
    }

    /* ---------------- scopes ---------------- */

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

    /** Não lidas em que o usuário foi CITADO — o aviso que ele não pode perder. */
    public function scopeMencionandoNaoLidas(Builder $query, User $user): Builder
    {
        return $query->naoLidasPor($user)
            ->whereHas('mencionados', fn (Builder $q) => $q->where('users.id', $user->id));
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
     * @param  \Illuminate\Support\Collection<int, User>  $usuarios  para destacar menções
     * @return array<string,mixed>
     */
    public function paraTela(User $usuarioAtual, $usuarios): array
    {
        $segmentos = self::segmentos($this->texto, $usuarios, $usuarioAtual->id);

        return [
            'id' => $this->id,
            'segmentos' => $segmentos,
            'autor' => $this->autor?->name ?? 'Usuário removido',
            'iniciais' => $this->iniciaisDoAutor(),
            'minha' => $this->user_id === $usuarioAtual->id,
            'hora' => $this->created_at->format('H:i'),
            'dia' => $this->created_at->format('d/m/Y'),
            'pode_apagar' => $this->podeSerApagadaPor($usuarioAtual),
            // Citou a mim: a tela marca a mensagem inteira.
            'me_citou' => collect($segmentos)->contains(fn (array $s) => $s['para_mim']),
        ];
    }
}
