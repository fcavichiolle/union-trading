<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Corretora;
use App\Rules\ListaDeEmails;
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
        // Nome e listas de e-mail são editáveis — mudar o TIPO de uma
        // corretora já usada não faz sentido, então ele fica de fora.
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
            // Destinatários da instrução de AA. Só fazem sentido nas NOSSAS
            // corretoras (é a nossa que executa o AA), mas a validação não
            // precisa saber disso: quem não usa, deixa em branco.
            'emails_para' => ['nullable', 'string', 'max:2000', new ListaDeEmails],
            'emails_copia' => ['nullable', 'string', 'max:2000', new ListaDeEmails],
        ], [
            'nome.required' => 'Informe o nome da corretora ou do broker.',
            'nome.unique' => 'Já existe um cadastro com este nome neste tipo.',
            'nome.max' => 'O nome passou do limite de 80 caracteres.',
            'tipo.required' => 'Escolha se é uma corretora nossa ou um broker de cliente.',
            'tipo.in' => 'Tipo inválido — escolha "Nossa corretora" ou "Broker do cliente".',
        ]);

        if (! $atual) {
            // O formulário de cadastro pede só nome e tipo; os e-mails são
            // preenchidos depois, na linha da corretora.
            return ['nome' => $dados['nome'], 'tipo' => $dados['tipo']];
        }

        return [
            'nome' => $dados['nome'],
            'emails_para' => $dados['emails_para'] ?? null,
            'emails_copia' => $dados['emails_copia'] ?? null,
        ];
    }
}
