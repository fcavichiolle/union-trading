<?php

namespace App\Http\Controllers\Contratos;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFixacaoRequest;
use App\Models\AuditLog;
use App\Models\Contrato;
use App\Models\Corretora;
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
            ->ativos() // contrato cancelado não tem mais o que fixar
            ->where('fixado', false)
            ->orderByDesc('data_contrato')
            ->orderByDesc('id')
            ->get();

        $fixacoes = Fixacao::with(['contrato', 'criadoPor'])
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('ny.index', [
            'contratos' => $contratos,
            'fixacoes' => $fixacoes,
            'posicao' => $this->posicaoPorTela($contratos),
            'corretoras' => Corretora::nossas()->orderBy('nome')->get(),
            'brokersCliente' => Corretora::doCliente()->orderBy('nome')->get(),
        ]);
    }

    /**
     * Posição de fixações por tela — mesmo formato do "VENDAS À FIXAR POR
     * REFERÊNCIA" da planilha de posição da mesa: para cada tela, os lotes/
     * sacas ainda a fixar (contratos pendentes, agrupados pelo mês de
     * fixação previsto) e, do lado fixado, os lotes já feitos naquela tela
     * com o level médio ponderado.
     *
     * @param \Illuminate\Support\Collection<int, Contrato> $pendentes
     * @return array<int, array<string, mixed>>
     */
    private function posicaoPorTela($pendentes): array
    {
        $linhas = [];

        // Lado "a fixar": lotes restantes por tela prevista (mes_fixacao).
        foreach ($pendentes as $c) {
            $tela = $c->mes_fixacao ?: 'SEM_TELA';
            $divisor = $c->tipo_cafe === 'CONILON' ? Contrato::DIVISOR_CONILON : Contrato::DIVISOR_ARABICA;

            $linhas[$tela] ??= $this->linhaVazia($tela, $c->porto);
            $linhas[$tela]['a_fixar_lotes'] += $c->lotesRestantes();
            $linhas[$tela]['a_fixar_sacas'] += $c->lotesRestantes() * $divisor;
        }

        // Lado "fixado": tranches agrupadas pela tela em que foram feitas.
        $fixadas = Fixacao::selectRaw('tela, SUM(lotes) AS lotes, SUM(level * lotes) AS soma_level')
            ->whereNotNull('tela')
            ->groupBy('tela')
            ->get();

        foreach ($fixadas as $f) {
            $porto = array_key_exists($f->tela, Contrato::mesesFixacaoVitoria()) ? 'VITORIA' : 'SANTOS';
            $linhas[$f->tela] ??= $this->linhaVazia($f->tela, $porto);
            $linhas[$f->tela]['fixado_lotes'] = (int) $f->lotes;
            $linhas[$f->tela]['level_medio'] = $f->lotes > 0 ? round($f->soma_level / $f->lotes, 2) : null;
        }

        // Ordena na sequência dos vencimentos (NY primeiro, depois Londres);
        // "sem tela definida" por último.
        $ordem = array_merge(array_keys(Contrato::mesesFixacaoSantos()), array_keys(Contrato::mesesFixacaoVitoria()));
        $posicaoNaOrdem = function (string $tela) use ($ordem): int {
            if ($tela === 'SEM_TELA') {
                return PHP_INT_MAX;
            }
            $i = array_search($tela, $ordem, true);

            return $i === false ? PHP_INT_MAX - 1 : $i;
        };
        uksort($linhas, fn ($a, $b) => $posicaoNaOrdem($a) <=> $posicaoNaOrdem($b));

        return array_values($linhas);
    }

    /** @return array<string, mixed> */
    private function linhaVazia(string $tela, string $porto): array
    {
        return [
            'tela' => $tela,
            'bolsa' => $porto === 'VITORIA' ? 'Londres' : 'NY ICE',
            'unidade' => $porto === 'VITORIA' ? 'USD/MT' : 'cts/lb',
            'a_fixar_lotes' => 0,
            'a_fixar_sacas' => 0.0,
            'fixado_lotes' => 0,
            'level_medio' => null,
        ];
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
                        . "({$fixacao->corretora}), preço {$fixacao->preco}.",
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
