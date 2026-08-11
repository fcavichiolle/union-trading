<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Qualidade;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cadastro de qualidades de café usadas no campo QUALITY dos contratos.
 * Restrito a admin (ver rotas).
 */
class QualidadeController extends Controller
{
    public function index(): View
    {
        $qualidades = Qualidade::orderBy('descricao')->paginate(30);

        return view('admin.qualidades.index', compact('qualidades'));
    }

    public function store(Request $request): RedirectResponse
    {
        Qualidade::create($this->validar($request));

        return redirect()->route('admin.qualidades.index')->with('status', 'Qualidade adicionada.');
    }

    public function update(Request $request, Qualidade $qualidade): RedirectResponse
    {
        $qualidade->update($this->validar($request));

        return redirect()->route('admin.qualidades.index')->with('status', 'Qualidade atualizada.');
    }

    public function destroy(Qualidade $qualidade): RedirectResponse
    {
        $qualidade->delete();

        return redirect()->route('admin.qualidades.index')->with('status', 'Qualidade removida.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'descricao' => ['required', 'string', 'max:200'],
        ], [
            'descricao.required' => 'Informe a descrição da qualidade (como sai no QUALITY do contrato).',
            'descricao.max' => 'A descrição passou do limite de 200 caracteres.',
        ]);
    }
}
