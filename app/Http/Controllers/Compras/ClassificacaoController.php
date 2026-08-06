<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClassificacaoRequest;
use App\Models\Compra;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ClassificacaoController extends Controller
{
    public function edit(Compra $compra): View
    {
        $classificacao = $compra->classificacao;

        return view('compras.classificacao', compact('compra', 'classificacao'));
    }

    public function update(StoreClassificacaoRequest $request, Compra $compra): RedirectResponse
    {
        $dados = $request->validated();
        $dados['created_by'] = Auth::id();

        // quantidade_lotes é recalculada automaticamente no model
        // (Classificacao::booted -> saving), então nunca aceitamos esse
        // valor vindo do formulário.
        $compra->classificacao()->updateOrCreate(['compra_id' => $compra->id], $dados);

        return redirect()->route('compras.show', $compra)->with('status', 'Classificação salva com sucesso.');
    }
}
