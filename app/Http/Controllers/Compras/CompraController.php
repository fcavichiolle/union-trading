<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompraRequest;
use App\Http\Requests\UpdateLoteRequest;
use App\Models\Compra;
use App\Models\Fornecedor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function index(Request $request): View
    {
        $compras = Compra::query()
            ->with(['fornecedor', 'classificacao', 'financeiro'])
            ->when($request->filled('mes_de'), function ($q) use ($request) {
                // Mês inicial do intervalo (<input type="month"> envia "YYYY-MM").
                $q->whereDate('mes_ano', '>=', $request->string('mes_de') . '-01');
            })
            ->when($request->filled('mes_ate'), function ($q) use ($request) {
                // Mês final do intervalo. mes_ano é sempre gravado no dia 01,
                // então <= o dia 01 do mês final inclui todo esse mês.
                $q->whereDate('mes_ano', '<=', $request->string('mes_ate') . '-01');
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
                    'sem_lote' => $q->semNumeroLote(),
                    'sem_classificacao' => $q->whereDoesntHave('classificacao'),
                    'sem_financeiro' => $q->whereDoesntHave('financeiro'),
                    'qualquer' => $q->comPendencia(),
                    default => null, // valor desconhecido não filtra nada
                };
            })
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = $request->string('busca');
                // Agrupado num where() próprio para não "vazar" o OR e brigar
                // com os filtros de mês/padrão acima.
                $q->where(function ($sub) use ($busca) {
                    $sub->where('uts', 'like', "%{$busca}%")
                        ->orWhereHas('fornecedor', fn ($f) => $f->where('nome', 'like', "%{$busca}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('compras.index', compact('compras'));
    }

    public function create(): View
    {
        return view('compras.create', [
            'armazens' => Compra::armazens(),
            'certificacoes' => Compra::certificacoes(),
        ]);
    }

    public function store(StoreCompraRequest $request): RedirectResponse
    {
        $dados = $request->validated();

        $compra = DB::transaction(function () use ($dados) {
            // Evita duplicar fornecedor: reaproveita pelo CNPJ se já existir.
            $fornecedor = Fornecedor::firstOrCreate(
                ['cnpj' => preg_replace('/\D/', '', $dados['fornecedor_cnpj'])],
                ['nome' => $dados['fornecedor_nome']]
            );

            return Compra::create([
                'uts' => $dados['uts'],
                'mes_ano' => date('Y-m-01', strtotime($dados['mes_ano'])),
                'fornecedor_id' => $fornecedor->id,
                'armazem' => $dados['armazem'],
                'certificacao' => $dados['certificacao'],
                'tipo_entrada' => $dados['tipo_entrada'] ?: 'BICA',
                'volume_sacas' => $dados['volume_sacas'],
                'created_by' => Auth::id(),
            ]);
        });

        return redirect()->route('compras.show', $compra)->with('status', 'Compra registrada com sucesso.');
    }

    public function show(Compra $compra): View
    {
        $compra->load(['fornecedor', 'classificacao', 'financeiro', 'criadoPor']);

        return view('compras.show', compact('compra'));
    }

    /**
     * Grava o número do lote (dado pelo armazém/controle de estoque).
     * Enquanto essa compra não tiver o número do lote, ela não pode ser
     * considerada definitivamente em estoque (ver Compra::precisaDeNumeroLote()).
     */
    public function atualizarLote(UpdateLoteRequest $request, Compra $compra): RedirectResponse
    {
        $compra->update($request->validated());

        return redirect()->route('compras.show', $compra)->with('status', 'Número do lote salvo com sucesso.');
    }
}