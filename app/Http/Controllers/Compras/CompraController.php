<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompraRequest;
use App\Models\AuditLog;
use App\Models\Compra;
use App\Models\Fornecedor;
use App\Services\ConsultaCnpj;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function index(Request $request): View
    {
        $compras = Compra::query()
            ->with(['fornecedor', 'classificacao', 'entregas'])
            ->withSum('entregas as sacas_entregues', 'volume_sacas')
            ->when($request->filled('mes_de'), function ($q) use ($request) {
                // O filtro é pela data da COMPRA (o negócio). Para recorte
                // por mês de entrada em armazém, use a tela de Estoque.
                $q->whereDate('data_compra', '>=', $request->string('mes_de') . '-01');
            })
            ->when($request->filled('mes_ate'), function ($q) use ($request) {
                $q->whereDate('data_compra', '<=', $request->string('mes_ate') . '-31');
            })
            ->when($request->filled('armazem'), function ($q) use ($request) {
                $q->whereHas('entregas', fn ($e) => $e->where('armazem', $request->string('armazem')));
            })
            ->when($request->filled('padrao'), function ($q) use ($request) {
                $padrao = $request->string('padrao')->toString();
                if ($padrao === 'SEM_CLASSIFICACAO') {
                    $q->whereDoesntHave('classificacao');
                } else {
                    $q->whereHas('classificacao', fn ($c) => $c->where('padrao_final', $padrao));
                }
            })
            ->when($request->filled('pendencia'), function ($q) use ($request) {
                // Atalhos vindos dos cards do painel inicial ("o que falta fazer").
                match ($request->string('pendencia')->toString()) {
                    'sem_lote' => $q->whereHas('entregas', fn ($e) => $e->semNumeroLote()),
                    'sem_classificacao' => $q->whereDoesntHave('classificacao'),
                    'sem_preco' => $q->semPreco(),
                    'saldo_a_entregar' => $q->comSaldoAEntregar(),
                    // Diferença (para cima ou para baixo) esperando decisão.
                    'divergente' => $q->naoLiquidadas()->has('entregas')->whereRaw(
                        'volume_contratado <> (select coalesce(sum(volume_sacas), 0) from entregas where entregas.compra_id = compras.id)'
                    ),
                    'sem_documento' => $q->whereHas('fornecedor', fn ($f) => $f->semDocumento()),
                    'qualquer' => $q->comPendencia(),
                    default => null, // valor desconhecido não filtra nada
                };
            })
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = $request->string('busca');
                // Agrupado num where() próprio para não "vazar" o OR e brigar
                // com os filtros acima.
                $q->where(function ($sub) use ($busca) {
                    $sub->where('uts', 'like', "%{$busca}%")
                        ->orWhereHas('fornecedor', fn ($f) => $f->where('nome', 'like', "%{$busca}%"));
                });
            })
            ->latest('data_compra')->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('compras.index', compact('compras'));
    }

    public function create(): View
    {
        return view('compras.create', ['compra' => null]);
    }

    public function store(StoreCompraRequest $request): RedirectResponse
    {
        $dados = $request->validated();

        $compra = DB::transaction(function () use ($dados) {
            $fornecedor = Fornecedor::localizarOuCriar(
                $dados['fornecedor_nome'],
                $dados['fornecedor_documento'] ?? null
            );

            return Compra::create([
                'uts' => $dados['uts'],
                'data_compra' => $dados['data_compra'],
                'fornecedor_id' => $fornecedor->id,
                'certificacao' => $dados['certificacao'],
                'logistica' => $dados['logistica'] ?? null,
                'tipo_entrada' => $dados['tipo_entrada'] ?: 'BICA',
                'volume_contratado' => $dados['volume_contratado'],
                'valor_saca' => $dados['valor_saca'] ?? null,
                'corretor_nome' => $dados['corretor_nome'] ?? null,
                'comissao_pct' => $dados['comissao_pct'] ?? null,
                'pagamento_previsto' => $dados['pagamento_previsto'] ?? null,
                'pagamento_obs' => $dados['pagamento_obs'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });

        return redirect()->route('compras.show', $compra)
            ->with('status', 'Compra registrada. Agora informe as entregas conforme o café for entrando no armazém.');
    }

    public function edit(Compra $compra): View
    {
        return view('compras.create', compact('compra'));
    }

    public function update(StoreCompraRequest $request, Compra $compra): RedirectResponse
    {
        $dados = $request->validated();

        DB::transaction(function () use ($dados, $compra) {
            $fornecedor = Fornecedor::localizarOuCriar(
                $dados['fornecedor_nome'],
                $dados['fornecedor_documento'] ?? null
            );

            $compra->update([
                'uts' => $dados['uts'],
                'data_compra' => $dados['data_compra'],
                'fornecedor_id' => $fornecedor->id,
                'certificacao' => $dados['certificacao'],
                'logistica' => $dados['logistica'] ?? null,
                'tipo_entrada' => $dados['tipo_entrada'] ?: 'BICA',
                'volume_contratado' => $dados['volume_contratado'],
                'valor_saca' => $dados['valor_saca'] ?? null,
                'corretor_nome' => $dados['corretor_nome'] ?? null,
                'comissao_pct' => $dados['comissao_pct'] ?? null,
                'pagamento_previsto' => $dados['pagamento_previsto'] ?? null,
                'pagamento_obs' => $dados['pagamento_obs'] ?? null,
            ]);
        });

        return redirect()->route('compras.show', $compra)->with('status', 'Compra atualizada.');
    }

    public function show(Compra $compra): View
    {
        $compra->load(['fornecedor', 'classificacao', 'criadoPor', 'entregas.criadoPor', 'liquidadaPor']);

        return view('compras.show', compact('compra'));
    }

    /**
     * Encerra a compra com o volume que realmente entrou. O armazém quase
     * nunca recebe exatamente o contratado — vieram 260 no lugar de 250, ou
     * 240 e ficou por isso mesmo. Liquidar é a decisão de que aquilo é o
     * final: os avisos de diferença param de aparecer.
     *
     * O volume contratado NÃO é sobrescrito: a diferença continua visível
     * como histórico (quebra ou excedente).
     */
    public function liquidar(Compra $compra): RedirectResponse
    {
        if ($compra->liquidada()) {
            return back()->with('status', 'Esta compra já estava liquidada.');
        }

        if ($compra->sacasEntregues() <= 0) {
            return back()->withErrors([
                'liquidacao' => 'Não há entrega lançada nesta UTS — lance a entrada no armazém antes de liquidar.',
            ]);
        }

        $compra->update(['liquidada_em' => now(), 'liquidada_por' => Auth::id()]);

        $entregues = number_format($compra->sacasEntregues(), 2, ',', '.');
        AuditLog::registrar(
            'compra_liquidada',
            "Compra UTS {$compra->uts} liquidada com {$entregues} sc "
                . '(contratado ' . number_format((float) $compra->volume_contratado, 2, ',', '.') . ' sc).',
            Auth::id()
        );

        return redirect()->route('compras.show', $compra)
            ->with('status', "UTS {$compra->uts} liquidada: o sistema passa a reconhecer {$entregues} sc.");
    }

    /** Desfaz a liquidação — a compra volta a acusar a diferença. */
    public function reabrir(Compra $compra): RedirectResponse
    {
        if (! $compra->liquidada()) {
            return back()->with('status', 'Esta compra não está liquidada.');
        }

        $compra->update(['liquidada_em' => null, 'liquidada_por' => null]);

        AuditLog::registrar('compra_reaberta', "Compra UTS {$compra->uts} reaberta.", Auth::id());

        return redirect()->route('compras.show', $compra)
            ->with('status', "UTS {$compra->uts} reaberta — a diferença entre contratado e entregue volta a aparecer.");
    }

    /**
     * Consulta de CNPJ para o formulário preencher o nome sozinho.
     * Conveniência: se a API falhar, devolve 404 e o usuário digita — o
     * lançamento nunca fica travado por isso (ver ConsultaCnpj).
     */
    public function consultarCnpj(string $cnpj, ConsultaCnpj $consulta): JsonResponse
    {
        $dados = $consulta->buscar($cnpj);

        return $dados === null
            ? response()->json(['erro' => 'CNPJ não encontrado.'], 404)
            : response()->json($dados);
    }
}
