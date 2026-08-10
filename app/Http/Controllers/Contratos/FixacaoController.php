<?php

namespace App\Http\Controllers\Contratos;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFixacaoRequest;
use App\Models\AuditLog;
use App\Models\Contrato;
use App\Models\Fixacao;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * "Tela NY": fixação de preço dos contratos A FIXAR, por lotes (tranches).
 * Registrar uma fixação = escolher o contrato, a corretora, quantos lotes,
 * o level da bolsa e o diferencial. O preço da tranche e a virada do
 * contrato para FIXED (média ponderada) são calculados no servidor.
 */
class FixacaoController extends Controller
{
    public function index(): View
    {
        // Contratos ainda não totalmente fixados, com o agregado de lotes
        // fixados carregado de uma vez (sem N+1 na listagem).
        $contratos = Contrato::with('cliente')
            ->withSum('fixacoes as lotes_fixados', 'lotes')
            ->where('fixado', false)
            ->orderByDesc('data_contrato')
            ->orderByDesc('id')
            ->get();

        $fixacoes = Fixacao::with(['contrato', 'criadoPor'])
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('ny.index', compact('contratos', 'fixacoes'));
    }

    public function store(StoreFixacaoRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $contratos = Contrato::whereIn('id', $dados['contratos'])->orderBy('id')->get();
        $grupo = $contratos->count() > 1;

        // Grupo (mesma tela/level/corretora p/ vários contratos): fixa TODOS
        // os lotes restantes de cada um; com 1 contrato, vale o campo `lotes`
        // (fixação parcial). Tudo ou nada: se algo falhar, nenhuma tranche fica.
        $totalLotes = DB::transaction(function () use ($contratos, $dados, $grupo) {
            $total = 0;
            foreach ($contratos as $contrato) {
                $lotes = $grupo ? $contrato->lotesRestantes() : (int) $dados['lotes'];

                $fixacao = Fixacao::create([
                    'contrato_id' => $contrato->id,
                    'corretora' => $dados['corretora'],
                    'broker_cliente' => $dados['broker_cliente'] ?? null,
                    'tela' => $dados['tela'],
                    'lotes' => $lotes,
                    'level' => $dados['level'],
                    'diferencial' => $dados['diferenciais'][$contrato->id],
                    'created_by' => Auth::id(),
                ]); // preco = level + diferencial (model)

                $contrato->recalcularFixacao();
                $total += $lotes;

                AuditLog::registrar(
                    'fixacao_registrada',
                    "Contrato UT {$contrato->numero_ut}: {$lotes} lote(s) @ {$fixacao->level} tela {$fixacao->tela} "
                        . "({$fixacao->corretoraLabel()}), preço {$fixacao->preco}.",
                    Auth::id()
                );
            }

            return $total;
        });

        if ($grupo) {
            $uts = $contratos->pluck('numero_ut')->map(fn ($n) => "UT {$n}")->implode(', ');
            $msg = "Fixação em grupo registrada — {$totalLotes} lote(s) na tela {$dados['tela']} ({$uts}). Todos os contratos ficaram FIXED.";
        } else {
            $contrato = $contratos->first()->refresh();
            $msg = $contrato->fixado
                ? "Fixação registrada — contrato UT {$contrato->numero_ut} totalmente FIXADO."
                : "Fixação registrada — restam {$contrato->lotesRestantes()} lote(s) no contrato UT {$contrato->numero_ut}.";
        }

        return redirect()->route('ny.index')->with('status', $msg);
    }

    public function destroy(Fixacao $fixacao): RedirectResponse
    {
        $contrato = $fixacao->contrato;
        $fixacao->delete();
        $contrato->recalcularFixacao(); // pode reverter FIXED -> A FIXAR

        AuditLog::registrar(
            'fixacao_excluida',
            "Contrato UT {$contrato->numero_ut}: tranche #{$fixacao->id} de {$fixacao->lotes} lote(s) excluída.",
            Auth::id()
        );

        return redirect()->route('ny.index')->with('status', 'Fixação excluída — o saldo do contrato foi recalculado.');
    }
}
