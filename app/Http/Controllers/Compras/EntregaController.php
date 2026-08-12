<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEntregaRequest;
use App\Models\Compra;
use App\Models\Entrega;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Entradas físicas no armazém. É a tela do funcionário 3: ele registra
 * quantas sacas realmente chegaram e o número do lote — e ajusta depois se
 * a conferência mudar (o volume pode ficar acima ou abaixo do contratado).
 */
class EntregaController extends Controller
{
    public function store(StoreEntregaRequest $request, Compra $compra): RedirectResponse
    {
        $entrega = $compra->entregas()->create($this->dados($request) + ['created_by' => Auth::id()]);

        return redirect()->route('compras.show', $compra)
            ->with('status', $this->resumo($compra->fresh(), $entrega, 'registrada'));
    }

    public function update(StoreEntregaRequest $request, Compra $compra, Entrega $entrega): RedirectResponse
    {
        abort_unless($entrega->compra_id === $compra->id, 404);

        $entrega->update($this->dados($request));

        return redirect()->route('compras.show', $compra)
            ->with('status', $this->resumo($compra->fresh(), $entrega, 'atualizada'));
    }

    public function destroy(Compra $compra, Entrega $entrega): RedirectResponse
    {
        abort_unless($entrega->compra_id === $compra->id, 404);

        $entrega->delete();

        return redirect()->route('compras.show', $compra)
            ->with('status', 'Entrega removida — o saldo a entregar da UTS foi recalculado.');
    }

    /**
     * A data é gravada como o funcionário informou — com o DIA. Antes ela
     * era normalizada para o dia 01 do mês, o que apagava exatamente a
     * informação que a auditoria procura: quando o café entrou.
     *
     * @return array<string, mixed>
     */
    private function dados(StoreEntregaRequest $request): array
    {
        return $request->validated();
    }

    /**
     * Mensagem que já responde a pergunta do funcionário 3: sobrou algo
     * para entregar, fechou certo, ou veio mais do que o contratado?
     */
    private function resumo(Compra $compra, Entrega $entrega, string $acao): string
    {
        $sacas = number_format((float) $entrega->volume_sacas, 2, ',', '.');
        $base = "Entrega de {$sacas} sc {$acao} ({$entrega->armazemLabel()}).";

        if ($compra->entregouAMais()) {
            $excedente = number_format(abs($compra->saldoAEntregar()), 2, ',', '.');

            return "{$base} Atenção: entraram {$excedente} sc a mais do que o contratado.";
        }

        if ($compra->totalmenteEntregue()) {
            return "{$base} A UTS {$compra->uts} está totalmente entregue.";
        }

        $saldo = number_format($compra->saldoAEntregar(), 2, ',', '.');

        return "{$base} Faltam {$saldo} sc para completar a UTS {$compra->uts}.";
    }
}
