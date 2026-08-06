<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinanceiroRequest;
use App\Models\Compra;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class FinanceiroController extends Controller
{
    public function edit(Compra $compra): View
    {
        $financeiro = $compra->financeiro;

        return view('compras.financeiro', compact('compra', 'financeiro'));
    }

    public function update(StoreFinanceiroRequest $request, Compra $compra): RedirectResponse
    {
        $dados = $request->validated();
        $dados['created_by'] = Auth::id();

        // valor_total é recalculado automaticamente no model
        // (FinanceiroCompra::booted -> saving) = valor_saca * volume_sacas.
        $compra->financeiro()->updateOrCreate(['compra_id' => $compra->id], $dados);

        return redirect()->route('compras.show', $compra)->with('status', 'Dados financeiros salvos com sucesso.');
    }
}
