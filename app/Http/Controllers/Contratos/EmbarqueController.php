<?php

namespace App\Http\Controllers\Contratos;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmbarqueRequest;
use App\Models\Armazem;
use App\Models\AuditLog;
use App\Models\Contrato;
use App\Models\Embarque;
use App\Models\Transportadora;
use App\Services\PosicaoVendas;
use App\Services\ProgramacaoEstufagem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Embarques: a agenda das estufagens e o controle de faturamento das
 * vendas. Substitui a aba VENDAS-FOB da planilha de posição e a planilha
 * do Google com o agendamento.
 *
 * A agenda é leitura para todo perfil (é o que a equipe consulta o dia
 * inteiro); lançar, editar e faturar é de admin/compras — ver as rotas.
 */
class EmbarqueController extends Controller
{
    /** Filtros da agenda, num lugar só (tela e links leem a mesma lista). */
    public const FILTROS = ['mes', 'status', 'armazem', 'busca'];

    /** Valor do filtro de status que mostra só o que ainda vai sair. */
    public const ABERTOS = 'abertos';

    public function index(Request $request, PosicaoVendas $posicao): View
    {
        $filtros = [
            'mes' => trim((string) $request->query('mes', '')),
            // Padrão: só o que ainda vai sair. O faturado é histórico e
            // esconderia a agenda do dia embaixo de meses de linhas.
            'status' => trim((string) $request->query('status', self::ABERTOS)),
            'armazem' => trim((string) $request->query('armazem', '')),
            'busca' => trim((string) $request->query('busca', '')),
        ];

        $embarques = Embarque::query()
            ->with(['contrato', 'armazem'])
            ->when(preg_match('/^\d{4}-\d{2}$/', $filtros['mes']), fn ($q) => $q->whereDate('mes_faturamento', $filtros['mes'] . '-01'))
            ->when($filtros['status'] === self::ABERTOS, fn ($q) => $q->emAberto())
            ->when(
                array_key_exists($filtros['status'], Embarque::statuses()),
                fn ($q) => $q->where('status', $filtros['status'])
            )
            ->when($filtros['armazem'] !== '', fn ($q) => $q->where('armazem_id', $filtros['armazem']))
            ->when($filtros['busca'] !== '', function ($q) use ($filtros) {
                $termo = '%' . $filtros['busca'] . '%';
                $q->whereHas('contrato', fn ($c) => $c
                    ->where('numero_ut', 'like', $termo)
                    ->orWhere('cliente_nome', 'like', $termo)
                    ->orWhere('buyer_ref', 'like', $termo));
            })
            ->orderBy('mes_faturamento')
            // Sem data de estufagem vai para o fim do mês: ainda não está
            // agendado, não pode aparecer antes do que sai amanhã.
            ->orderByRaw('data_estufagem IS NULL')
            ->orderBy('data_estufagem')
            ->orderBy('id')
            ->get();

        return view('embarques.index', [
            'porMes' => $embarques->groupBy(fn (Embarque $e) => $e->mes_faturamento->format('Y-m')),
            'total' => $embarques->count(),
            'filtros' => $filtros,
            'semFiltro' => $filtros['mes'] === '' && $filtros['status'] === self::ABERTOS
                && $filtros['armazem'] === '' && $filtros['busca'] === '',
            'aProgramar' => $this->contratosAProgramar(),
            'posicao' => $posicao->calcular(),
            'podeEditar' => $request->user()->hasRole('admin', 'compras'),
        ]);
    }

    /**
     * Agendamentos: os embarques em aberto na ordem em que as estufagens
     * acontecem. É a planilha do Google "EMBARQUES" — a pergunta aqui é
     * "o que sai e quando", enquanto o controle logístico responde "em que
     * pé está cada venda do mês".
     */
    public function agenda(Request $request): View
    {
        $armazem = trim((string) $request->query('armazem', ''));

        $abertos = Embarque::query()
            ->emAberto()
            ->with(['contrato', 'armazem'])
            ->when($armazem !== '', fn ($q) => $q->where('armazem_id', $armazem))
            ->orderBy('data_estufagem')
            ->orderBy('id')
            ->get();

        [$agendados, $semData] = $abertos->partition(fn (Embarque $e) => $e->data_estufagem !== null);

        return view('embarques.agenda', [
            // Por dia: a agenda é lida "o que tem hoje, o que tem amanhã".
            'porDia' => $agendados->groupBy(fn (Embarque $e) => $e->data_estufagem->format('Y-m-d')),
            'semData' => $semData->values(),
            'armazem' => $armazem,
            'podeEditar' => $request->user()->hasRole('admin', 'compras'),
        ]);
    }

    /**
     * Contratos com volume que ainda não virou embarque. Aparecem na agenda
     * porque são venda de verdade sem logística nenhuma — e o que não está
     * na tela não é programado.
     *
     * @return \Illuminate\Support\Collection<int, Contrato>
     */
    private function contratosAProgramar()
    {
        return Contrato::ativos()
            ->with('embarques')
            ->orderBy('embarque_mes')
            ->orderBy('numero_ut')
            ->get()
            ->filter(fn (Contrato $c) => $c->sacasAProgramar() >= 1)
            ->values();
    }

    public function create(Contrato $contrato): View
    {
        $contrato->load('embarques');

        // Já nasce com o que falta do contrato e o mês dele: na maioria dos
        // contratos o embarque é um só, e redigitar seria o retrabalho que
        // este módulo veio tirar.
        $embarque = new Embarque([
            'quantidade_kg' => max(0, $contrato->kgAProgramar()),
            'mes_faturamento' => $contrato->embarque_mes ?? now()->startOfMonth(),
            'status' => 'ABERTO',
        ]);

        return view('embarques.form', [
            'contrato' => $contrato,
            'embarque' => $embarque,
            'armazens' => Armazem::lista(),
            'transportadoras' => Transportadora::orderBy('nome')->pluck('nome'),
            'programacao' => null,
        ]);
    }

    public function store(EmbarqueRequest $request, Contrato $contrato): RedirectResponse
    {
        $embarque = new Embarque($request->dados());
        $embarque->contrato()->associate($contrato);
        $embarque->created_by = Auth::id();
        $embarque->save();

        AuditLog::registrar(
            'embarque_criado',
            "Embarque {$embarque->referencia()} lançado: {$embarque->quantidade_kg} kg, "
                . "faturamento {$embarque->mes_faturamento->format('m/Y')}.",
            Auth::id()
        );

        return redirect()->route('contratos.show', $contrato)
            ->with('status', "Embarque {$embarque->referencia()} lançado.");
    }

    public function edit(Embarque $embarque): View
    {
        return view('embarques.form', [
            'contrato' => $embarque->contrato->load('embarques'),
            'embarque' => $embarque,
            'armazens' => Armazem::lista(),
            'transportadoras' => Transportadora::orderBy('nome')->pluck('nome'),
            'programacao' => ProgramacaoEstufagem::de($embarque, Auth::user()),
        ]);
    }

    /**
     * PDF da programação de estufagem — o que o armazém imprime. O e-mail
     * sai por `mailto:` (texto puro) e leva este arquivo em anexo.
     */
    public function programacao(Embarque $embarque): Response
    {
        $programacao = ProgramacaoEstufagem::de($embarque, Auth::user());

        $pdf = Pdf::loadView('embarques.programacao-pdf', [
            'programacao' => $programacao,
            'geradoPor' => Auth::user()?->name,
        ])->setPaper('a4', 'landscape'); // 15 colunas: retrato não cabe

        AuditLog::registrar(
            'programacao_estufagem_gerada',
            "Programação de estufagem gerada para {$embarque->referencia()}.",
            Auth::id()
        );

        return $pdf->download($programacao->nomeDoArquivo());
    }

    public function update(EmbarqueRequest $request, Embarque $embarque): RedirectResponse
    {
        $antes = $embarque->status;
        $embarque->update($request->dados());

        AuditLog::registrar(
            'embarque_atualizado',
            "Embarque {$embarque->referencia()} atualizado"
                . ($antes !== $embarque->status ? " ({$antes} → {$embarque->status})" : '') . '.',
            Auth::id()
        );

        return redirect()->route('embarques.index')
            ->with('status', "Embarque {$embarque->referencia()} atualizado.");
    }

    /**
     * Faturar direto da agenda: é a ação mais frequente e a que tira a venda
     * da posição, então não merece um formulário inteiro.
     */
    public function faturar(Request $request, Embarque $embarque): RedirectResponse
    {
        $dados = $request->validate(
            ['faturado_em' => ['nullable', 'date', 'before_or_equal:today']],
            ['faturado_em.before_or_equal' => 'A data do faturamento não pode ser no futuro.']
        );

        $embarque->status = 'FATURADO';
        $embarque->faturado_em = Carbon::parse($dados['faturado_em'] ?? now())->toDateString();
        $embarque->save();

        AuditLog::registrar(
            'embarque_faturado',
            "Embarque {$embarque->referencia()} faturado em {$embarque->faturado_em->format('d/m/Y')} "
                . "({$embarque->sacas} sc saíram da posição).",
            Auth::id()
        );

        return back()->with('status', "Embarque {$embarque->referencia()} marcado como faturado — saiu da posição de vendas.");
    }

    public function destroy(Embarque $embarque): RedirectResponse
    {
        $referencia = $embarque->referencia();
        $contrato = $embarque->contrato;
        $embarque->delete();

        AuditLog::registrar(
            'embarque_excluido',
            "Embarque {$referencia} excluído ({$embarque->quantidade_kg} kg, status {$embarque->status}).",
            Auth::id()
        );

        return redirect()->route('contratos.show', $contrato)
            ->with('status', "Embarque {$referencia} excluído — o volume voltou para \"a programar\" no contrato.");
    }
}
