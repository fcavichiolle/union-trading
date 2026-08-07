<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cadastro de clientes (BUYER) dos contratos. Restrito a admin (ver rotas).
 */
class ClienteController extends Controller
{
    public function index(): View
    {
        $clientes = Cliente::orderBy('nome')->paginate(20);

        return view('admin.clientes.index', compact('clientes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validar($request);
        Cliente::create($dados);

        return redirect()->route('admin.clientes.index')->with('status', 'Cliente adicionado.');
    }

    public function edit(Cliente $cliente): View
    {
        return view('admin.clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($this->validar($request));

        return redirect()->route('admin.clientes.index')->with('status', 'Cliente atualizado.');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        $cliente->delete();

        return redirect()->route('admin.clientes.index')->with('status', 'Cliente removido.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'endereco' => ['required', 'string', 'max:500'],
            'ref_padrao' => ['nullable', 'string', 'max:120'],
        ]);
    }
}
