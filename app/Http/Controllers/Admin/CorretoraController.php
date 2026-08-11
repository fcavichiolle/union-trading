<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Corretora;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cadastro de corretoras (nossas) e brokers dos clientes, usados nos
 * dropdowns da Tela NY. Restrito a admin (ver rotas). As fixações guardam
 * o nome como snapshot, então editar/excluir aqui não mexe no histórico.
 */
class CorretoraController extends Controller
{
    public function index(): View
    {
        return view('admin.corretoras.index', [
            'nossas' => Corretora::nossas()->orderBy('nome')->get(),
            'doCliente' => Corretora::doCliente()->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Corretora::create($this->validar($request));

        return redirect()->route('admin.corretoras.index')->with('status', 'Corretora adicionada.');
    }

    public function update(Request $request, Corretora $corretora): RedirectResponse
    {
        // Só o nome é editável — mudar o tipo de uma corretora usada não faz sentido.
        $corretora->update($this->validar($request, $corretora));

        return redirect()->route('admin.corretoras.index')->with('status', 'Corretora atualizada.');
    }

    public function destroy(Corretora $corretora): RedirectResponse
    {
        $corretora->delete();

        return redirect()->route('admin.corretoras.index')
            ->with('status', 'Corretora removida do cadastro (fixações antigas não são alteradas).');
    }

    private function validar(Request $request, ?Corretora $atual = null): array
    {
        // No update o tipo não vem do formulário — mantém o existente.
        $tipo = $atual->tipo ?? $request->input('tipo');

        $dados = $request->validate([
            'nome' => [
                'required', 'string', 'max:80',
                Rule::unique('corretoras', 'nome')
                    ->where('tipo', $tipo)
                    ->ignore($atual?->id),
            ],
            'tipo' => [$atual ? 'nullable' : 'required', Rule::in(array_keys(Corretora::tipos()))],
        ], [
            'nome.required' => 'Informe o nome da corretora ou do broker.',
            'nome.unique' => 'Já existe um cadastro com este nome neste tipo.',
            'nome.max' => 'O nome passou do limite de 80 caracteres.',
            'tipo.required' => 'Escolha se é uma corretora nossa ou um broker de cliente.',
            'tipo.in' => 'Tipo inválido — escolha "Nossa corretora" ou "Broker do cliente".',
        ]);

        return $atual ? ['nome' => $dados['nome']] : $dados;
    }
}
