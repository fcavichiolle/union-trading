<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinanceiroRequest;
use App\Models\Compra;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Tela enxuta do perfil FINANCEIRO. Preço, corretor e comissão são dados
 * da negociação e vivem na própria compra (a tabela financeiro_compras foi
 * removida) — mas o perfil financeiro não edita compras, então esta tela
 * existe para ele ajustar só esses três campos.
 */
class FinanceiroController extends Controller
{
    public function edit(Compra $compra): View
    {
        return view('compras.financeiro', compact('compra'));
    }

    public function update(StoreFinanceiroRequest $request, Compra $compra): RedirectResponse
    {
        $compra->update($request->validated());

        return redirect()->route('compras.show', $compra)->with('status', 'Dados financeiros salvos com sucesso.');
    }
}
