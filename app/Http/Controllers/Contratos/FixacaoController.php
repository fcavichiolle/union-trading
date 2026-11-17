<?php

namespace App\Http\Controllers\Contratos;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFixacaoRequest;
use App\Models\AuditLog;
use App\Models\Contrato;
use App\Models\Corretora;
use App\Models\Fixacao;
use App\Services\InstrucaoAA;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Tela NY": fixação de preço dos contratos A FIXAR, por lotes (tranches).
 * Registrar uma fixação = escolher o contrato, a corretora, quantos lotes,
 * o level da bolsa e o diferencial. O preço da tranche e a virada do
 * contrato para FIXED (média ponderada) são calculados no servidor.
 */
class FixacaoController extends Controller
{
    /** Quantas fixações a tela mostra quando nenhum filtro está ativo. */
    private const ULTIMAS = 15;

    public function index(Request $request): View
    {
        // TODOS os contratos pendentes, sem filtro: é desta lista que sai a
        // "Posição de fixações por tela", que é um retrato geral. Filtrar o
        // card junto com a tabela daria uma posição parcial com cara de total.
        $pendentes = $this->contratosPendentes()->get();

        $filtrosContratos = $this->filtros($request, self::FILTROS_CONTRATOS);

        return view('ny.index', [
            'contratos' => $this->contratosPendentes($filtrosContratos)->get(),
            'filtrosContratos' => $filtrosContratos,
            'semFiltroContratos' => $this->semFiltro($filtrosContratos),
            'compradores' => $pendentes->pluck('cliente_nome')->unique()->sort()->values()->all(),
            'telasPendentes' => $this->telasPendentes($pendentes),
            'posicao' => $this->posicaoPorTela($pendentes),
            'corretoras' => Corretora::nossas()->orderBy('nome')->get(),
            'brokersCliente' => Corretora::doCliente()->orderBy('nome')->get(),
        ]);
    }

    /**
     * Histórico de fixações, em página própria (item "Fixações registradas"
     * no menu). Saiu da Tela NY porque lá é onde se FAZ a fixação — a lista
     * do que já foi feito é consulta, e dividia a atenção de quem estava
     * operando; junto com os dois filtros na mesma tela, virou página longa.
     */
    public function fixacoes(Request $request): View
    {
        $filtros = $this->filtros($request, self::FILTROS);

        // Sem filtro, a lista é um "o que aconteceu por último" e fica curta.
        // Com filtro, ela vira uma busca — e cortar em 15 esconderia justamente
        // o que a pessoa procura (uma tranche antiga daquele contrato).
        $fixacoes = $this->consulta($filtros)
            ->when($this->semFiltro($filtros), fn ($q) => $q->limit(self::ULTIMAS))
            ->get();

        return view('ny.fixacoes', [
            'fixacoes' => $fixacoes,
            'filtros' => $filtros,
            'semFiltro' => $this->semFiltro($filtros),
            'ultimas' => self::ULTIMAS,
            'corretorasFixadas' => $this->corretorasFixadas(),
            'contratosFixados' => $this->contratosFixados(),
            'instrucoesAA' => $this->instrucoesAA($fixacoes),
        ]);
    }

    /** Código usado no filtro para "contrato sem tela definida". */
    public const SEM_TELA = 'SEM_TELA';

    /**
     * Contratos ainda não totalmente fixados, com o agregado de lotes
     * fixados carregado de uma vez (sem N+1 na listagem).
     *
     * @param array<string, string> $filtros
     * @return \Illuminate\Database\Eloquent\Builder<Contrato>
     */
    private function contratosPendentes(array $filtros = [])
    {
        $filtros += ['ut' => '', 'comprador' => '', 'tela' => ''];

        return Contrato::with('cliente')
            ->withSum('fixacoes as lotes_fixados', 'lotes')
            ->ativos() // contrato cancelado não tem mais o que fixar
            ->where('fixado', false)
            ->when($filtros['ut'] !== '', fn ($q) => $q->where('numero_ut', 'like', '%' . $filtros['ut'] . '%'))
            // Comprador pelo snapshot do contrato, não pelo cadastro: é o
            // nome que a tela mostra, e contrato de cliente já removido
            // continua achável.
            ->when($filtros['comprador'] !== '', fn ($q) => $q->where('cliente_nome', $filtros['comprador']))
            ->when(
                $filtros['tela'] !== '',
                fn ($q) => $filtros['tela'] === self::SEM_TELA
                    ? $q->whereNull('mes_fixacao')
                    : $q->where('mes_fixacao', $filtros['tela'])
            )
            ->orderByDesc('data_contrato')
            ->orderByDesc('id');
    }

    /**
     * Telas que aparecem nos contratos pendentes, na ordem dos vencimentos —
     * e não a lista inteira da bolsa, que encheria o dropdown de posições
     * sem nenhum contrato atrás.
     *
     * @param Collection<int, Contrato> $pendentes
     * @return array<string, string> código => rótulo
     */
    private function telasPendentes(Collection $pendentes): array
    {
        $usadas = $pendentes->pluck('mes_fixacao')->filter()->unique();

        $ordem = array_keys(Contrato::mesesFixacaoTodas());
        $opcoes = [];

        foreach ($ordem as $codigo) {
            if ($usadas->contains($codigo)) {
                $opcoes[$codigo] = Contrato::rotuloDaTela($codigo) ?? $codigo;
            }
        }

        // Tela fora da janela conhecida (posição muito antiga) não pode sumir
        // do filtro só porque não está na lista de ordenação.
        foreach ($usadas as $codigo) {
            $opcoes[$codigo] ??= $codigo;
        }

        if ($pendentes->contains(fn (Contrato $c) => blank($c->mes_fixacao))) {
            $opcoes[self::SEM_TELA] = 'Sem tela definida';
        }

        return $opcoes;
    }

    /**
     * Mesmos filtros para a tela e para o PDF — um só lugar, senão o
     * arquivo compartilhado sairia com um recorte diferente do que está
     * sendo visto, que é a pior forma de errar num relatório.
     */
    public const FILTROS = ['corretora', 'contrato'];

    /** Filtros da tabela "Contratos a fixar" (outro conjunto, mesma tela). */
    public const FILTROS_CONTRATOS = ['ut', 'comprador', 'tela'];

    /**
     * @param array<int, string> $nomes
     * @return array<string, string>
     */
    private function filtros(Request $request, array $nomes): array
    {
        $filtros = [];

        foreach ($nomes as $nome) {
            $filtros[$nome] = trim((string) $request->query($nome, ''));
        }

        return $filtros;
    }

    /** @param array<string, string> $filtros */
    private function semFiltro(array $filtros): bool
    {
        return collect($filtros)->every(fn (string $v) => $v === '');
    }

    /**
     * @param array<string, string> $filtros
     * @return \Illuminate\Database\Eloquent\Builder<Fixacao>
     */
    private function consulta(array $filtros)
    {
        return Fixacao::with(['contrato.cliente', 'criadoPor'])
            // A corretora é snapshot (string), então o filtro casa pelo nome
            // gravado — inclusive o de um cadastro já renomeado ou excluído.
            ->when($filtros['corretora'] !== '', fn ($q) => $q->where('corretora', $filtros['corretora']))
            ->when($filtros['contrato'] !== '', fn ($q) => $q->where('contrato_id', $filtros['contrato']))
            // Pela DATA da fixação, não pela ordem de cadastro: as tranches
            // trazidas da planilha entraram todas no mesmo dia, com a data
            // real de cada uma, e "últimas" por id mostraria as de setembro.
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Corretoras que aparecem nas fixações — não as do cadastro. Filtrar por
     * uma opção sem nenhuma fixação é clique perdido, e o histórico pode ter
     * nome que saiu do cadastro.
     *
     * @return array<int, string>
     */
    private function corretorasFixadas(): array
    {
        return Fixacao::query()
            ->select('corretora')
            ->distinct()
            ->orderBy('corretora')
            ->pluck('corretora')
            ->all();
    }

    /** @return array<int, Contrato> contratos que têm ao menos uma fixação */
    private function contratosFixados(): array
    {
        return Contrato::whereHas('fixacoes')
            ->orderByDesc('numero_ut')
            ->get(['id', 'numero_ut', 'cliente_nome'])
            ->all();
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

            $linhas[$tela] ??= $this->linhaVazia($tela, $c->porto);
            $linhas[$tela]['a_fixar_lotes'] += $c->lotesRestantes();
            // Sacas REAIS do contrato, não lotes × 283,49 (ver sacasDeLotes).
            $linhas[$tela]['a_fixar_sacas'] += $c->sacasDeLotes($c->lotesRestantes());
        }

        // Lado "fixado": tranches agrupadas pela tela em que foram feitas. O
        // level médio é ponderado pelas SACAS de cada tranche — é assim que a
        // planilha de posição faz, e o lote inteiro distorceria o peso.
        $somaLevel = [];
        foreach (Fixacao::with('contrato')->whereNotNull('tela')->get() as $f) {
            if (! $f->contrato) {
                continue;
            }

            // Janela larga: fixação antiga pode estar numa posição vencida, e
            // com a lista "em aberto" ela seria classificada como Santos por
            // engano (o `else` do array_key_exists).
            $porto = Contrato::telaEhDeLondres($f->tela) ? 'VITORIA' : 'SANTOS';
            $sacas = $f->contrato->sacasDeLotes((int) $f->lotes);

            $linhas[$f->tela] ??= $this->linhaVazia($f->tela, $porto);
            $linhas[$f->tela]['fixado_lotes'] += (int) $f->lotes;
            $linhas[$f->tela]['fixado_sacas'] += $sacas;
            $somaLevel[$f->tela] = ($somaLevel[$f->tela] ?? 0) + (float) $f->level * $sacas;
        }

        foreach ($somaLevel as $tela => $soma) {
            $sacas = $linhas[$tela]['fixado_sacas'];
            $linhas[$tela]['level_medio'] = $sacas > 0 ? round($soma / $sacas, 2) : null;
        }

        // Ordena na sequência dos vencimentos (NY primeiro, depois Londres);
        // "sem tela definida" por último.
        // Janela larga na ordenação também, senão posição vencida cai no
        // fim da lista em vez de ficar na sequência dos vencimentos.
        $ordem = array_merge(
            array_keys(Contrato::mesesFixacaoSantosTodas()),
            array_keys(Contrato::mesesFixacaoVitoriaTodas())
        );
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
            'fixado_sacas' => 0.0,
            'level_medio' => null,
        ];
    }

    /**
     * Instrução de AA de cada tranche listada, para o botão "AA" da tabela.
     *
     * Duas sutilezas: a janela das últimas 15 pode cortar um grupo ao meio,
     * e aí a instrução sairia sem parte dos contratos do negócio — por isso
     * os grupos são recarregados inteiros; e tranches do mesmo grupo mas de
     * clientes diferentes apontam para instruções diferentes, porque o
     * e-mail é sempre "Union X um cliente".
     *
     * @param Collection<int, Fixacao> $fixacoes
     * @return array<int, InstrucaoAA> fixacao_id => instrução
     */
    private function instrucoesAA(Collection $fixacoes): array
    {
        $grupos = $fixacoes->pluck('grupo')->filter()->unique();

        $todas = $grupos->isEmpty()
            ? $fixacoes
            : Fixacao::with('contrato.cliente')->whereIn('grupo', $grupos)->get()
                ->concat($fixacoes->whereNull('grupo'))
                ->unique('id');

        // Fixação sem grupo (registrada antes desta tela existir) vira um
        // balde só dela, para não se misturar com as outras.
        $chave = fn (Fixacao $f) => ($f->grupo ?: 'f:' . $f->id)
            . '|' . ($f->contrato?->cliente_id ?: 'n:' . $f->contrato?->cliente_nome);

        $mapa = [];

        foreach ($todas->groupBy($chave) as $doCliente) {
            $instrucao = InstrucaoAA::deFixacoes($doCliente, Auth::user())[0] ?? null;

            if ($instrucao === null) {
                continue;
            }

            foreach ($doCliente as $f) {
                $mapa[$f->id] = $instrucao;
            }
        }

        return $mapa;
    }

    /**
     * PDF das fixações registradas, no mesmo recorte da tela. Sem o corte
     * das últimas 15: quem baixa o arquivo quer o conjunto inteiro do
     * filtro, não uma amostra.
     */
    public function pdf(Request $request): Response
    {
        $filtros = $this->filtros($request, self::FILTROS);
        $fixacoes = $this->consulta($filtros)->get();

        $pdf = Pdf::loadView('ny.fixacoes-pdf', [
            'fixacoes' => $fixacoes,
            'filtros' => $this->filtrosPorExtenso($filtros, $fixacoes),
            'totais' => $this->totaisPorUnidade($fixacoes),
            'geradoPor' => Auth::user()?->name,
        ])->setPaper('a4', 'landscape'); // retrato não cabe as 10 colunas

        AuditLog::registrar(
            'fixacoes_pdf_gerado',
            'PDF de fixações registradas gerado (' . $fixacoes->count() . ' registro(s)).',
            Auth::id()
        );

        return $pdf->download($this->nomeDoArquivo($filtros));
    }

    /**
     * Filtros em texto, para o PDF dizer o que está mostrando. Um relatório
     * filtrado que não conta o recorte vira número sem contexto na mão de
     * quem recebe.
     *
     * @param array<string, string> $filtros
     * @param Collection<int, Fixacao> $fixacoes
     * @return array<int, string>
     */
    private function filtrosPorExtenso(array $filtros, Collection $fixacoes): array
    {
        $texto = [];

        if ($filtros['corretora'] !== '') {
            $texto[] = 'Corretora: ' . $filtros['corretora'];
        }

        if ($filtros['contrato'] !== '') {
            $ut = $fixacoes->first()?->contrato?->numero_ut
                ?? Contrato::find($filtros['contrato'])?->numero_ut;
            $texto[] = 'Contrato: UT ' . ($ut ?? $filtros['contrato']);
        }

        return $texto;
    }

    /**
     * Totais por unidade de preço. **Não existe um total único**: Santos é
     * cts/lb e Vitória é USD/MT, e somar/mediar os dois daria um número que
     * parece certo e não significa nada. Por isso uma linha por unidade.
     *
     * @param Collection<int, Fixacao> $fixacoes
     * @return array<int, array<string, mixed>>
     */
    private function totaisPorUnidade(Collection $fixacoes): array
    {
        return $fixacoes
            ->groupBy(fn (Fixacao $f) => $f->contrato?->unidadePreco() ?? '—')
            ->map(function (Collection $doGrupo, string $unidade) {
                $lotes = (int) $doGrupo->sum('lotes');

                return [
                    'unidade' => $unidade,
                    'tranches' => $doGrupo->count(),
                    'lotes' => $lotes,
                    // Média PONDERADA pelos lotes — a simples trataria uma
                    // tranche de 1 lote igual a uma de 12.
                    'level_medio' => $lotes > 0
                        ? round($doGrupo->sum(fn (Fixacao $f) => (float) $f->level * $f->lotes) / $lotes, 2)
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /** @param array<string, string> $filtros */
    private function nomeDoArquivo(array $filtros): string
    {
        $partes = ['Fixacoes'];

        foreach ($filtros as $valor) {
            if ($valor !== '') {
                $partes[] = Str::slug($valor);
            }
        }

        $partes[] = now()->format('d-m-Y');

        return implode('_', $partes) . '.pdf';
    }

    public function store(StoreFixacaoRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $contratos = Contrato::whereIn('id', $dados['contratos'])->orderBy('id')->get();
        $grupo = $contratos->count() > 1;

        // Marca as tranches deste envio: é o que permite remontar depois o
        // e-mail de AA do negócio inteiro, e não de um contrato solto.
        $idGrupo = (string) Str::uuid();
        // Coleção do Eloquent (não `collect()`): é ela que sabe fazer
        // `load()` das relações para montar a instrução depois.
        $criadas = new EloquentCollection();

        // Grupo (mesma tela/level/corretora p/ vários contratos): fixa TODOS
        // os lotes restantes de cada um; com 1 contrato, vale o campo `lotes`
        // (fixação parcial). Tudo ou nada: se algo falhar, nenhuma tranche fica.
        $totalLotes = DB::transaction(function () use ($contratos, $dados, $grupo, $idGrupo, $criadas) {
            $total = 0;
            foreach ($contratos as $contrato) {
                $lotes = $grupo ? $contrato->lotesRestantes() : (int) $dados['lotes'];

                $fixacao = Fixacao::create([
                    'contrato_id' => $contrato->id,
                    'grupo' => $idGrupo,
                    'corretora' => $dados['corretora'],
                    'broker_cliente' => $dados['broker_cliente'] ?? null,
                    'tela' => $dados['tela'],
                    'lotes' => $lotes,
                    'level' => $dados['level'],
                    'diferencial' => $dados['diferenciais'][$contrato->id],
                    'created_by' => Auth::id(),
                ]); // preco = level + diferencial (model)

                $contrato->recalcularFixacao();
                $criadas->push($fixacao);
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

        return redirect()->route('ny.index')
            ->with('status', $msg)
            ->with('aa', $this->paraAFlash($criadas));
    }

    /**
     * Versão "burra" das instruções para a sessão: só arrays, porque o flash
     * é serializado e guardar objeto de domínio ali envelhece mal.
     *
     * @param Collection<int, Fixacao> $criadas
     * @return array<int, array<string, mixed>>
     */
    private function paraAFlash(Collection $criadas): array
    {
        $criadas->load('contrato.cliente');

        return array_map(fn (InstrucaoAA $i) => [
            'cliente' => $i->cliente,
            'assunto' => $i->assunto(),
            'corpo' => $i->corpo(),
            'mailto' => $i->mailto(),
            'enviavel' => $i->enviavel(),
            'avisos' => $i->avisos(),
        ], InstrucaoAA::deFixacoes($criadas, Auth::user()));
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
