<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Canal geral de mensagens da equipe (mural interno).
 *
 * Todo usuário interno lê e escreve — os perfis do sistema limitam o que se
 * ALTERA nos registros, não a conversa da equipe.
 *
 * A tela busca mensagens novas de tempo em tempo (polling em `novas`), no
 * mesmo espírito da página de Cotações. Sem WebSocket: exigiria um processo
 * rodando sempre ao lado do PHP, e o ganho de 3 segundos não paga isso.
 *
 * O texto fica CIFRADO no banco (ver o cast em Mensagem) — por isso as
 * menções vivem numa tabela própria: o MySQL não sabe procurar "@Fulano"
 * dentro de conteúdo cifrado.
 */
class MensagemController extends Controller
{
    /** O que a tela precisa de cada mensagem — carregado junto, sem N+1. */
    private const RELACOES = ['autor', 'reacoes.usuario'];

    public function index(): View
    {
        $usuario = Auth::user();
        $usuarios = $this->usuariosDoCanal();

        $mensagens = Mensagem::with(self::RELACOES)
            ->latest('id')
            ->limit(Mensagem::POR_PAGINA)
            ->get()
            // Mais antigas em cima, como em qualquer conversa.
            ->sortBy('id')
            ->values();

        // Quantas estavam sem ler ANTES de marcar como lido — é o que a tela
        // usa para desenhar a linha "novas mensagens".
        $naoLidas = Mensagem::naoLidasPor($usuario)->count();

        $this->marcarComoLido();

        return view('mensagens.index', [
            'mensagens' => $mensagens->map(fn (Mensagem $m) => $m->paraTela($usuario, $usuarios)),
            'naoLidas' => $naoLidas,
            'temAnteriores' => $mensagens->isNotEmpty()
                && Mensagem::where('id', '<', $mensagens->first()['id'] ?? 0)->exists(),
            // Alimenta o autocomplete do "@".
            'usuarios' => $usuarios->map(fn (User $u) => [
                'id' => $u->id,
                'nome' => $u->name,
                'perfil' => $u->role?->nome,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $dados = $request->validate(
            ['texto' => ['required', 'string', 'max:2000']],
            [
                'texto.required' => 'Escreva a mensagem antes de enviar.',
                'texto.max' => 'A mensagem passou do limite de 2.000 caracteres.',
            ]
        );

        $usuarios = $this->usuariosDoCanal();

        // As menções são gravadas pelo próprio model (evento `saved`), então
        // qualquer caminho que crie mensagem já sai com elas.
        $mensagem = Mensagem::create([
            'user_id' => Auth::id(),
            'texto' => $dados['texto'],
        ]);

        // Quem escreve está com o canal aberto: não faz sentido a própria
        // mensagem aparecer como não lida no badge dele.
        $this->marcarComoLido();

        if ($request->expectsJson()) {
            return response()->json([
                'mensagem' => $mensagem->load(self::RELACOES)->paraTela(Auth::user(), $usuarios),
            ]);
        }

        return redirect()->route('mensagens.index');
    }

    /**
     * Mensagens novas desde um id (polling da tela) e as anteriores a um id
     * (botão "carregar anteriores"). Um endpoint só, porque é a mesma
     * pergunta em duas direções.
     */
    public function novas(Request $request): JsonResponse
    {
        $usuario = Auth::user();
        $usuarios = $this->usuariosDoCanal();

        // Abertura do chat flutuante: a página mais recente, mais o que a
        // tela cheia recebe pronta do servidor (a lista para o "@").
        if ($request->boolean('recentes')) {
            $mensagens = Mensagem::with(self::RELACOES)
                ->latest('id')
                ->limit(Mensagem::POR_PAGINA)
                ->get()
                ->sortBy('id')
                ->values();

            $naoLidas = Mensagem::naoLidasPor($usuario)->count();
            $this->marcarComoLido();

            return response()->json([
                'mensagens' => $mensagens->map(fn (Mensagem $m) => $m->paraTela($usuario, $usuarios))->all(),
                'tem_anteriores' => $mensagens->isNotEmpty()
                    && Mensagem::where('id', '<', $mensagens->first()->id)->exists(),
                'nao_lidas' => $naoLidas,
                'usuarios' => $usuarios->map(fn (User $u) => [
                    'id' => $u->id, 'nome' => $u->name, 'perfil' => $u->role?->nome,
                ])->values()->all(),
            ]);
        }

        if ($request->filled('antes')) {
            $mensagens = Mensagem::with(self::RELACOES)
                ->where('id', '<', $request->integer('antes'))
                ->latest('id')
                ->limit(Mensagem::POR_PAGINA)
                ->get()
                ->sortBy('id')
                ->values();

            return response()->json([
                'mensagens' => $mensagens->map(fn (Mensagem $m) => $m->paraTela($usuario, $usuarios))->all(),
                'tem_anteriores' => $mensagens->isNotEmpty()
                    && Mensagem::where('id', '<', $mensagens->first()->id)->exists(),
            ]);
        }

        $mensagens = Mensagem::with(self::RELACOES)
            ->depoisDe($request->integer('depois'))
            ->orderBy('id')
            ->limit(200)
            ->get();

        // A tela está aberta na frente do usuário: o que chega já está lido.
        $this->marcarComoLido();

        return response()->json([
            'mensagens' => $mensagens->map(fn (Mensagem $m) => $m->paraTela($usuario, $usuarios))->all(),
            // Reação em mensagem antiga não é "mensagem nova": a tela manda
            // de onde começa o que está aberto (`visiveis_de`) e recebe as
            // reações desse trecho — quem não vier na lista ficou sem reação.
            'reacoes' => $request->filled('visiveis_de')
                ? $this->reacoesDesde($request->integer('visiveis_de'), $usuario)
                : null,
        ]);
    }

    /**
     * Reagir a uma mensagem (ou tirar a reação, clicando de novo). Qualquer
     * pessoa do canal reage a qualquer mensagem.
     */
    public function reagir(Request $request, Mensagem $mensagem): JsonResponse
    {
        $dados = $request->validate(
            ['emoji' => ['required', 'string', Rule::in(Mensagem::REACOES)]],
            ['emoji.in' => 'Reação inválida.', 'emoji.required' => 'Escolha uma reação.']
        );

        $mensagem->alternarReacao(Auth::user(), $dados['emoji']);

        return response()->json([
            'id' => $mensagem->id,
            'reacoes' => $mensagem->load('reacoes.usuario')->reacoesParaTela(Auth::user()),
        ]);
    }

    /**
     * Quantas mensagens o usuário ainda não leu — para o ícone flutuante
     * das outras telas. Não marca nada como lido: só olhar o contador não
     * é abrir o canal.
     */
    public function resumo(): JsonResponse
    {
        $usuario = Auth::user();

        return response()->json([
            'nao_lidas' => Mensagem::naoLidasPor($usuario)->count(),
            'citado' => Mensagem::mencionandoNaoLidas($usuario)->exists(),
        ]);
    }

    /** @return array<int, array<int, array<string, mixed>>> id da mensagem => reações */
    private function reacoesDesde(int $id, User $usuario): array
    {
        return Mensagem::with('reacoes.usuario')
            ->where('id', '>=', $id)
            ->whereHas('reacoes')
            // Teto: `visiveis_de` vem do navegador. Sem limite, um pedido
            // com 0 varreria o histórico inteiro a cada 10 segundos.
            ->latest('id')
            ->limit(300)
            ->get()
            ->mapWithKeys(fn (Mensagem $m) => [$m->id => $m->reacoesParaTela($usuario)])
            ->all();
    }

    public function destroy(Request $request, Mensagem $mensagem): RedirectResponse|JsonResponse
    {
        $usuario = Auth::user();

        abort_unless($mensagem->podeSerApagadaPor($usuario), 403);

        // Admin apagando mensagem de outra pessoa fica registrado — mas SEM o
        // texto: com o conteúdo cifrado no banco, guardar uma cópia em claro
        // no log de auditoria seria uma porta dos fundos para o que a
        // criptografia veio proteger.
        if ($mensagem->user_id !== $usuario->id) {
            AuditLog::registrar(
                'mensagem.excluida',
                sprintf(
                    'Mensagem #%d de %s (escrita em %s) excluída por um administrador. '
                        . 'O conteúdo não é registrado aqui de propósito.',
                    $mensagem->id,
                    $mensagem->autor?->name ?? 'usuário removido',
                    $mensagem->created_at->format('d/m/Y H:i')
                )
            );
        }

        $mensagem->delete();

        // Pelo chat (fetch) a mensagem some no lugar, sem recarregar — no
        // ícone flutuante, redirecionar tiraria a pessoa da tela em que está.
        if ($request->expectsJson()) {
            return response()->json(['removida' => $mensagem->id]);
        }

        return redirect()->route('mensagens.index')->with('status', 'Mensagem removida.');
    }

    /**
     * Quem pode ser citado: usuários ativos, que são os que entram no
     * sistema. Ordenados por nome para o autocomplete ficar previsível.
     *
     * @return Collection<int, User>
     */
    private function usuariosDoCanal(): Collection
    {
        return User::with('role')->where('active', true)->orderBy('name')->get();
    }

    /** Marca o canal como lido para o usuário atual. */
    private function marcarComoLido(): void
    {
        Auth::user()->forceFill(['mensagens_lidas_em' => now()])->saveQuietly();
    }
}
