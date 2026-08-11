<?php

namespace App\Http\Controllers\Contratos;

use App\Http\Controllers\Controller;
use App\Http\Requests\MotivoContratoRequest;
use App\Http\Requests\StoreContratoRequest;
use App\Http\Requests\UpdateContratoRequest;
use App\Models\AuditLog;
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
        $contratos = Contrato::with('cliente')
            ->withSum('fixacoes as lotes_fixados', 'lotes') // p/ badge PARCIAL sem N+1
            ->orderByDesc('data_contrato')->orderByDesc('id')->paginate(20);

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
        $dados = $this->prepararDados($request->validated(), $request->boolean('fixado'));
        $dados['created_by'] = Auth::id();

        // sacas, lotes, containers e kg_por_container são calculados no model.
        $contrato = Contrato::create($dados);

        return redirect()->route('contratos.show', $contrato)->with('status', 'Contrato criado com sucesso.');
    }

    public function edit(Contrato $contrato): View
    {
        $contrato->loadSum('fixacoes as lotes_fixados', 'lotes');

        return view('contratos.edit', [
            'contrato' => $contrato,
            'clientes' => Cliente::orderBy('nome')->get(),
            'qualidades' => Qualidade::orderBy('descricao')->get(),
        ]);
    }

    public function update(UpdateContratoRequest $request, Contrato $contrato): RedirectResponse
    {
        $dados = $this->prepararDados($request->validated(), $request->boolean('fixado'));

        $contrato->update($dados);

        // Só recalcula o estado de fixação se existirem tranches: um
        // contrato marcado como FIXED na mão (sem tranche) seria zerado por
        // recalcularFixacao(), que assume as tranches como fonte da verdade.
        if ($contrato->fixacoes()->exists()) {
            $contrato->recalcularFixacao();
        }

        AuditLog::registrar('contrato_editado', "Contrato UT {$contrato->numero_ut} editado.", Auth::id());

        return redirect()->route('contratos.show', $contrato)->with('status', 'Contrato atualizado com sucesso.');
    }

    /**
     * Cancela o contrato: o registro fica (histórico e PDF continuam
     * acessíveis), mas sai da posição — some da Tela NY e dos números do
     * painel. Fixações já registradas NÃO são apagadas: a operação de
     * mercado aconteceu de verdade e continua no histórico.
     */
    public function cancelar(MotivoContratoRequest $request, Contrato $contrato): RedirectResponse
    {
        if ($contrato->cancelado()) {
            return back()->with('status', 'Este contrato já estava cancelado.');
        }

        $contrato->update([
            'cancelado_em' => now(),
            'motivo_cancelamento' => $request->string('motivo')->toString(),
            'cancelado_por' => Auth::id(),
        ]);

        AuditLog::registrar(
            'contrato_cancelado',
            "Contrato UT {$contrato->numero_ut} cancelado. Motivo: {$contrato->motivo_cancelamento}",
            Auth::id()
        );

        return redirect()->route('contratos.show', $contrato)
            ->with('status', "Contrato UT {$contrato->numero_ut} cancelado.");
    }

    /**
     * Desfaz um cancelamento feito por engano: o contrato volta a valer e
     * reaparece na Tela NY e nos números do painel. O motivo do
     * cancelamento sai do registro, mas a história continua no AuditLog —
     * tanto o cancelamento quanto esta reativação ficam lá.
     */
    public function reativar(MotivoContratoRequest $request, Contrato $contrato): RedirectResponse
    {
        if (! $contrato->cancelado()) {
            return back()->with('status', 'Este contrato já estava ativo.');
        }

        $motivoAnterior = $contrato->motivo_cancelamento;

        $contrato->update([
            'cancelado_em' => null,
            'motivo_cancelamento' => null,
            'cancelado_por' => null,
        ]);

        AuditLog::registrar(
            'contrato_reativado',
            "Contrato UT {$contrato->numero_ut} reativado. Motivo: {$request->string('motivo')}. "
                . "(Cancelamento anterior: {$motivoAnterior})",
            Auth::id()
        );

        return redirect()->route('contratos.show', $contrato)
            ->with('status', "Contrato UT {$contrato->numero_ut} reativado — voltou para a posição.");
    }

    /**
     * Exclui de vez — só para contrato lançado errado, que não deveria
     * existir. Bloqueado quando já existem fixações: elas seriam apagadas
     * em cascata junto com o contrato, e apagar registro de operação de
     * mercado é perda de informação real. Nesse caso o caminho é cancelar.
     */
    public function destroy(MotivoContratoRequest $request, Contrato $contrato): RedirectResponse
    {
        $fixacoes = $contrato->fixacoes()->count();

        if ($fixacoes > 0) {
            return back()->withErrors([
                'motivo' => "Este contrato tem {$fixacoes} fixação(ões) registrada(s) na Tela NY e não pode ser excluído "
                    . '— apagá-lo apagaria essas operações. Use "Cancelar contrato" para tirá-lo da posição mantendo o histórico.',
            ]);
        }

        $ut = $contrato->numero_ut;
        $motivo = $request->string('motivo')->toString();

        $contrato->delete();

        // O registro some do banco: o AuditLog é o único lugar onde o
        // motivo e a existência do contrato continuam registrados.
        AuditLog::registrar('contrato_excluido', "Contrato UT {$ut} excluído. Motivo: {$motivo}", Auth::id());

        return redirect()->route('contratos.index')->with('status', "Contrato UT {$ut} excluído.");
    }

    public function show(Contrato $contrato): View
    {
        $contrato->load(['fixacoes.criadoPor', 'canceladoPor']);

        return view('contratos.show', compact('contrato'));
    }

    public function pdf(Contrato $contrato): Response
    {
        $pdf = Pdf::loadView('contratos.pdf', compact('contrato'))->setPaper('a4');

        return $pdf->download($contrato->nomeArquivoPdf());
    }

    /**
     * Snapshot do cliente/qualidade e limpeza dos campos do modo de preço
     * não usado — as duas regras valem igual na criação e na edição.
     */
    private function prepararDados(array $dados, bool $fixado): array
    {
        // Snapshot: se o cadastro mudar depois, o contrato permanece intacto.
        $cliente = Cliente::findOrFail($dados['cliente_id']);
        $qualidade = Qualidade::findOrFail($dados['qualidade_id']);

        $dados['cliente_nome'] = $cliente->nome;
        $dados['cliente_endereco'] = $cliente->endereco;
        $dados['qualidade_descricao'] = $qualidade->descricao;

        // Checkbox HTML: só vem no request quando marcado. Normaliza para
        // bool e limpa os campos do modo que NÃO foi usado, para não deixar
        // dado morto de um modo "vazando" no outro.
        $dados['fixado'] = $fixado;
        if ($fixado) {
            $dados['diferencial'] = null;
            $dados['mes_fixacao'] = null;
        } else {
            $dados['preco_fixado'] = null;
            $dados['preco_fixado_unidade'] = null;
        }

        if (! empty($dados['embarque_mes'])) {
            $dados['embarque_mes'] = Carbon::createFromFormat('Y-m', $dados['embarque_mes'])->startOfMonth();
        }

        return $dados;
    }
}
