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

        // Conilon não tem padrão/bebida — o formulário nem mostra os campos.
        if ($compra->ehConilon()) {
            $dados['padrao_final'] = null;
            $dados['tipo_bebida'] = null;
        }

        // quantidade_lotes é recalculada automaticamente no model
        // (Classificacao::booted -> saving), então nunca aceitamos esse
        // valor vindo do formulário.
        $compra->classificacao()->updateOrCreate(['compra_id' => $compra->id], $dados);

        // A conferência é a palavra final sobre a qualidade: se o
        // classificador corrigiu o padrão, a compra acompanha, senão as duas
        // telas passariam a mostrar padrões diferentes.
        if (! $compra->ehConilon()) {
            $compra->update([
                'padrao_final' => $dados['padrao_final'],
                'tipo_bebida' => $dados['tipo_bebida'],
            ]);
        }

        return redirect()->route('compras.show', $compra)->with('status', 'Classificação salva com sucesso.');
    }
}
