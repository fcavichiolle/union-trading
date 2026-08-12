<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Armazem;
use App\Rules\DocumentoValido;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cadastro dos armazéns usados nas compras e entregas. Restrito a admin
 * (ver rotas).
 *
 * Diferente das corretoras, a entrega aponta para o CADASTRO (armazem_id) e
 * não guarda o nome como snapshot: renomear o armazém deve atualizar o
 * histórico, porque é o mesmo lugar físico com nome novo — o contrário
 * partiria o Estoque em dois grupos para o mesmo galpão.
 *
 * Por isso excluir é BLOQUEADO quando existe entrega ou compra apontando
 * para ele (a FK é restrictOnDelete; aqui a tela explica antes de tentar).
 */
class ArmazemController extends Controller
{
    public function index(): View
    {
        return view('admin.armazens.index', [
            'armazens' => Armazem::withCount(['entregas', 'compras'])->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Armazem::create($this->validar($request));

        return redirect()->route('admin.armazens.index')->with('status', 'Armazém cadastrado.');
    }

    public function update(Request $request, Armazem $armazem): RedirectResponse
    {
        $armazem->update($this->validar($request, $armazem));

        return redirect()->route('admin.armazens.index')
            ->with('status', 'Armazém atualizado (as entregas apontam para o cadastro, então o histórico acompanha o nome novo).');
    }

    public function destroy(Armazem $armazem): RedirectResponse
    {
        // Em uso não sai: apagar levaria embora a referência de entregas
        // que já contam como estoque.
        if ($armazem->entregas()->exists() || $armazem->compras()->exists()) {
            return redirect()->route('admin.armazens.index')->withErrors([
                'armazem' => 'Não é possível excluir ' . $armazem->nome
                    . ': existem compras ou entregas registradas nele. Renomeie o cadastro se o armazém mudou de nome.',
            ]);
        }

        $armazem->delete();

        return redirect()->route('admin.armazens.index')->with('status', 'Armazém removido do cadastro.');
    }

    /** @return array<string,mixed> */
    private function validar(Request $request, ?Armazem $atual = null): array
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120', Rule::unique('armazens', 'nome')->ignore($atual?->id)],
            'cidade' => ['required', 'string', 'max:120'],
            'estado' => ['required', Rule::in(Armazem::estados())],
            'endereco' => ['nullable', 'string', 'max:200'],
            // Opcional, como no fornecedor — mas validado quando preenchido.
            'documento' => ['nullable', 'string', new DocumentoValido()],
        ], [
            'nome.required' => 'Informe o nome do armazém.',
            'nome.unique' => 'Já existe um armazém com este nome.',
            'cidade.required' => 'Informe a cidade do armazém.',
            'estado.required' => 'Selecione o estado (UF).',
            'estado.in' => 'Selecione um estado válido.',
        ]);

        // Guardado só com dígitos, igual ao documento do fornecedor.
        $dados['documento'] = \App\Models\Fornecedor::apenasDigitos($dados['documento'] ?? null);

        return $dados;
    }
}
