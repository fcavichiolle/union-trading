<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Mensagem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Canal geral de mensagens da equipe (mural interno).
 *
 * Todo usuário interno lê e escreve — os perfis do sistema limitam o que se
 * ALTERA nos registros, não a conversa da equipe.
 *
 * A tela busca mensagens novas de tempo em tempo (polling em `novas`), no
 * mesmo espírito da página de Cotações. Sem WebSocket: exigiria um processo
 * rodando sempre ao lado do PHP, e o ganho de 3 segundos não paga isso.
 */
class MensagemController extends Controller
{
    public function index(): View
    {
        $mensagens = Mensagem::with('autor')
            ->latest('id')
            ->limit(Mensagem::POR_PAGINA)
            ->get()
            // Mais antigas em cima, como em qualquer conversa.
            ->sortBy('id')
            ->values();

        $usuario = Auth::user();

        // Quantas estavam sem ler ANTES de marcar como lido — é o que a tela
        // usa para desenhar a linha "novas mensagens".
        $naoLidas = Mensagem::naoLidasPor($usuario)->count();

        $this->marcarComoLido();

        return view('mensagens.index', [
            'mensagens' => $mensagens->map(fn (Mensagem $m) => $m->paraTela($usuario)),
            'naoLidas' => $naoLidas,
            'temAnteriores' => $mensagens->isNotEmpty()
                && Mensagem::where('id', '<', $mensagens->first()['id'] ?? 0)->exists(),
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

        $mensagem = Mensagem::create([
            'user_id' => Auth::id(),
            'texto' => $dados['texto'],
        ]);

        // Quem escreve está com o canal aberto: não faz sentido a própria
        // mensagem aparecer como não lida no badge dele.
        $this->marcarComoLido();

        if ($request->expectsJson()) {
            return response()->json(['mensagem' => $mensagem->load('autor')->paraTela(Auth::user())]);
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

        if ($request->filled('antes')) {
            $mensagens = Mensagem::with('autor')
                ->where('id', '<', $request->integer('antes'))
                ->latest('id')
                ->limit(Mensagem::POR_PAGINA)
                ->get()
                ->sortBy('id')
                ->values();

            return response()->json([
                'mensagens' => $mensagens->map(fn (Mensagem $m) => $m->paraTela($usuario))->all(),
                'tem_anteriores' => $mensagens->isNotEmpty()
                    && Mensagem::where('id', '<', $mensagens->first()->id)->exists(),
            ]);
        }

        $mensagens = Mensagem::with('autor')
            ->depoisDe($request->integer('depois'))
            ->orderBy('id')
            ->limit(200)
            ->get();

        // A tela está aberta na frente do usuário: o que chega já está lido.
        $this->marcarComoLido();

        return response()->json([
            'mensagens' => $mensagens->map(fn (Mensagem $m) => $m->paraTela($usuario))->all(),
        ]);
    }

    public function destroy(Mensagem $mensagem): RedirectResponse
    {
        $usuario = Auth::user();

        abort_unless($mensagem->podeSerApagadaPor($usuario), 403);

        // Admin apagando mensagem de outra pessoa fica registrado: é o único
        // lugar onde o conteúdo sobrevive depois que a linha sai do canal.
        if ($mensagem->user_id !== $usuario->id) {
            AuditLog::registrar(
                'mensagem.excluida',
                sprintf(
                    'Mensagem de %s excluída pelo admin: "%s"',
                    $mensagem->autor?->name ?? 'usuário removido',
                    \Illuminate\Support\Str::limit($mensagem->texto, 200)
                )
            );
        }

        $mensagem->delete();

        return redirect()->route('mensagens.index')->with('status', 'Mensagem removida.');
    }

    /** Marca o canal como lido para o usuário atual. */
    private function marcarComoLido(): void
    {
        Auth::user()->forceFill(['mensagens_lidas_em' => now()])->saveQuietly();
    }
}
