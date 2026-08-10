<?php

namespace App\Http\Controllers\Contratos;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContratoRequest;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Qualidade;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ContratoController extends Controller
{
    public function index(): View
    {
        $contratos = Contrato::with('cliente')->orderByDesc('data_contrato')->orderByDesc('id')->paginate(20);

        return view('contratos.index', compact('contratos'));
    }

    public function create(): View
    {
        return view('contratos.create', [
            'clientes' => Cliente::orderBy('nome')->get(),
            'qualidades' => Qualidade::orderBy('descricao')->get(),
        ]);
    }

    public function store(StoreContratoRequest $request): RedirectResponse
    {
        $dados = $request->validated();

        // Snapshot do cliente e da qualidade no momento da criação: se o
        // cadastro mudar depois, o contrato antigo permanece intacto.
        $cliente = Cliente::findOrFail($dados['cliente_id']);
        $qualidade = Qualidade::findOrFail($dados['qualidade_id']);

        $dados['cliente_nome'] = $cliente->nome;
        $dados['cliente_endereco'] = $cliente->endereco;
        $dados['qualidade_descricao'] = $qualidade->descricao;
        $dados['created_by'] = Auth::id();

        // Checkbox HTML: só vem no request quando marcado. Normaliza para
        // bool e limpa os campos do modo que NÃO foi usado, para não deixar
        // dado morto de um modo "vazando" no outro.
        $dados['fixado'] = $request->boolean('fixado');
        if ($dados['fixado']) {
            $dados['diferencial'] = null;
            $dados['mes_fixacao'] = null;
        } else {
            $dados['preco_fixado'] = null;
            $dados['preco_fixado_unidade'] = null;
        }

        if (! empty($dados['embarque_mes'])) {
            $dados['embarque_mes'] = Carbon::createFromFormat('Y-m', $dados['embarque_mes'])->startOfMonth();
        }

        // sacas, lotes, containers e kg_por_container são calculados no model.
        $contrato = Contrato::create($dados);

        return redirect()->route('contratos.show', $contrato)->with('status', 'Contrato criado com sucesso.');
    }

    public function show(Contrato $contrato): View
    {
        return view('contratos.show', compact('contrato'));
    }

    public function pdf(Contrato $contrato): Response
    {
        $pdf = Pdf::loadView('contratos.pdf', compact('contrato'))->setPaper('a4');

        return $pdf->download($contrato->nomeArquivoPdf());
    }
}
