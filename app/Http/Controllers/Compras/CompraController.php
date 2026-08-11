<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompraRequest;
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
        $compra->load(['fornecedor', 'classificacao', 'criadoPor', 'entregas.criadoPor']);

        return view('compras.show', compact('compra'));
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
