<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transportadora;
use App\Rules\ListaDeEmails;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cadastro de transportadoras: o nome sugerido no embarque e os e-mails
 * que recebem a programação de estufagem. Restrito a admin (ver rotas).
 * O embarque guarda o nome como texto, então editar/excluir aqui não mexe
 * no histórico.
 */
class TransportadoraController extends Controller
{
    public function index(): View
    {
        return view('admin.transportadoras.index', [
            'transportadoras' => Transportadora::orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Transportadora::create($this->validar($request));

        return redirect()->route('admin.transportadoras.index')->with('status', 'Transportadora adicionada.');
    }

    public function update(Request $request, Transportadora $transportadora): RedirectResponse
    {
        $transportadora->update($this->validar($request, $transportadora));

        return redirect()->route('admin.transportadoras.index')->with('status', 'Transportadora atualizada.');
    }

    public function destroy(Transportadora $transportadora): RedirectResponse
    {
        $transportadora->delete();

        return redirect()->route('admin.transportadoras.index')
            ->with('status', 'Transportadora removida do cadastro (embarques antigos não são alterados).');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Transportadora $atual = null): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:60', Rule::unique('transportadoras', 'nome')->ignore($atual?->id)],
            'emails' => ['nullable', 'string', 'max:2000', new ListaDeEmails],
        ], [
            'nome.required' => 'Informe o nome da transportadora.',
            'nome.unique' => 'Já existe uma transportadora com este nome.',
        ]);
    }
}
