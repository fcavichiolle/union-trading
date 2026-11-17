<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\Contrato;
use App\Models\Embarque;
use App\Models\Entrega;
use App\Models\FotoEstoque;

/**
 * Posição Long & Short — a aba POSIÇAO_LONG SHORT da planilha de posição.
 *
 * Por padrão (standard), em sacas:
 *
 *   foto do estoque (medido + rendimento estimado da bica/reaproveitamento)
 *   + entregas posteriores à foto          (café que entrou depois)
 *   − baixas posteriores à foto            (café que saiu depois)
 *   = ESTOQUE HOJE
 *   + compras a receber                    (saldo das UTS em aberto)
 *   = DISPONÍVEL
 *   − vendas em aberto, mês a mês          (PosicaoVendas: tudo sem baixa)
 *   = POSIÇÃO  (positivo = long, negativo = short)
 *
 * O que muda em relação à planilha:
 *  - lá, compra e venda saíam da conta quando alguém apagava a linha depois
 *    de atualizar o estoque. Aqui a foto tem DATA e o que é posterior a ela
 *    entra sozinho — não há linha para apagar;
 *  - lá, as compras do mês entravam na coluna do mês SEGUINTE (a mesa
 *    confirmou que era desalinhamento). Aqui entram já no disponível;
 *  - venda atrasada (mês que passou e ainda não saiu) pesa no primeiro mês.
 *
 * Compra e estoque de bica viram padrão por RENDIMENTO: o da compra vem de
 * `Compra::rendimentoPrevisto()`; o da bica em estoque, de
 * `config/posicao.php` (os mesmos percentuais do rodapé da aba GERAL
 * ESTOQUE).
 */
final class LongShort
{
    public function __construct(private readonly PosicaoVendas $vendas)
    {
    }

    /**
     * @return array{
     *     foto: ?FotoEstoque,
     *     colunas: array<string, string>,
     *     linhas: array<string, array<string, mixed>>,
     *     totais: array<string, mixed>,
     *     interno: array<string, float>,
     *     semPreparo: array<string, float>,
     *     avisos: array<int, string>
     * }
     */
    public function calcular(): array
    {
        $foto = FotoEstoque::atual();
        $standards = Contrato::standards();
        $mi = FotoEstoque::MERCADO_INTERNO;
        $baldes = array_keys($standards + [$mi => 'Mercado interno']);

        $zero = array_fill_keys($baldes, 0.0);
        $medido = $estimado = $entradas = $aReceber = $baixas = $zero;
        $avisos = [];

        // ---------- 1. Foto do estoque ----------
        $semPreparo = [];
        $semBalde = 0.0;
        if ($foto) {
            $porBalde = $foto->lotes()->selectRaw('balde, sum(sacas) as sacas')->groupBy('balde')->pluck('sacas', 'balde');

            foreach ($porBalde as $balde => $sacas) {
                $sacas = (float) $sacas;
                if (isset($medido[$balde])) {
                    $medido[$balde] += $sacas;
                } elseif (in_array($balde, FotoEstoque::SEM_PREPARO, true)) {
                    $semPreparo[$balde] = $sacas;
                    foreach (config("posicao.rendimento_estoque.{$balde}") as $destino => $pct) {
                        $estimado[$destino] += $sacas * $pct / 100;
                    }
                } else {
                    $semBalde += $sacas;
                }
            }

            if ($semBalde > 0) {
                $avisos[] = 'A foto do estoque tem ' . number_format($semBalde, 0, ',', '.')
                    . ' sc em lotes sem balde da posição (padrão do Digisul sem de/para) — não entram na conta.';
            }
        } else {
            $avisos[] = 'Ainda não há foto do estoque lançada: o lado comprado mostra só as compras.';
        }

        // ---------- 2. Compras: entregas depois da foto e saldo a receber ----------
        $pelaRegraGeral = [];
        $conilon = 0.0;

        $compras = Compra::with(['entregas', 'classificacao'])->get();
        foreach ($compras as $compra) {
            // A foto vale como abertura do dia: entrega do dia já é "depois".
            $depois = (float) $compra->entregas
                ->filter(fn (Entrega $e) => ! $foto || $e->data_entrega->gte($foto->data_foto))
                ->sum('volume_sacas');
            $saldo = $compra->liquidada() ? 0.0 : max(0.0, $compra->saldoAEntregar());

            if ($depois <= 0 && $saldo <= 0) {
                continue;
            }

            $rendimento = $compra->rendimentoPrevisto();
            if ($rendimento === null) {
                $conilon += $depois + $saldo;
                continue;
            }
            if ($rendimento['origem'] === 'padrao') {
                $pelaRegraGeral[] = $compra->uts;
            }

            foreach ($rendimento['baldes'] as $balde => $fracao) {
                $entradas[$balde] += $depois * $fracao;
                $aReceber[$balde] += $saldo * $fracao;
            }
        }

        if ($pelaRegraGeral !== []) {
            $avisos[] = 'Compras sem % de 17/18 e de cata (entraram pelo rendimento padrão): '
                . implode(', ', $pelaRegraGeral) . '.';
        }
        if ($conilon > 0) {
            $avisos[] = number_format($conilon, 0, ',', '.') . ' sc de conilon em compras não entram nesta posição (é só arábica).';
        }

        // ---------- 3. Baixas depois da foto ----------
        $baixasSemStandard = 0.0;
        if ($foto) {
            $baixados = Embarque::baixados()->whereDate('baixado_em', '>=', $foto->data_foto)->with('contrato')->get();
            foreach ($baixados as $embarque) {
                $standard = $embarque->contrato?->standard;
                if ($standard && isset($baixas[$standard])) {
                    $baixas[$standard] += (float) $embarque->sacas;
                } else {
                    $baixasSemStandard += (float) $embarque->sacas;
                }
            }
            if ($baixasSemStandard > 0) {
                $avisos[] = number_format($baixasSemStandard, 0, ',', '.')
                    . ' sc baixadas depois da foto são de contratos sem standard — não foram tiradas de nenhum padrão.';
            }
        }

        // ---------- 4. Vendas em aberto e a posição mês a mês ----------
        $vendas = $this->vendas->calcular();
        $colunas = $vendas['colunas'];

        $linhas = [];
        foreach ($standards as $codigo => $rotulo) {
            $estoqueFoto = $medido[$codigo] + $estimado[$codigo];
            $estoqueHoje = $estoqueFoto + $entradas[$codigo] - $baixas[$codigo];
            $disponivel = $estoqueHoje + $aReceber[$codigo];

            $vendasDoStandard = $vendas['linhas'][$codigo]['valores'] ?? [];
            $saldo = $disponivel;
            $posicaoPorMes = [];
            foreach (array_keys($colunas) as $coluna) {
                $saldo -= (float) ($vendasDoStandard[$coluna] ?? 0);
                $posicaoPorMes[$coluna] = round($saldo, 2);
            }

            $linhas[$codigo] = [
                'rotulo' => $rotulo,
                'medido' => round($medido[$codigo], 2),
                'estimado' => round($estimado[$codigo], 2),
                'estoque_foto' => round($estoqueFoto, 2),
                'entradas' => round($entradas[$codigo], 2),
                'baixas' => round($baixas[$codigo], 2),
                'estoque_hoje' => round($estoqueHoje, 2),
                'a_receber' => round($aReceber[$codigo], 2),
                'disponivel' => round($disponivel, 2),
                'vendas' => round(array_sum($vendasDoStandard), 2),
                'vendas_por_mes' => $vendasDoStandard,
                'posicao_por_mes' => $posicaoPorMes,
                'posicao' => round($saldo, 2),
            ];
        }

        $totais = [];
        foreach (['medido', 'estimado', 'estoque_foto', 'entradas', 'baixas', 'estoque_hoje', 'a_receber', 'disponivel', 'vendas', 'posicao'] as $campo) {
            $totais[$campo] = round(array_sum(array_column($linhas, $campo)), 2);
        }
        foreach (array_keys($colunas) as $coluna) {
            $totais['posicao_por_mes'][$coluna] = round(array_sum(array_map(fn ($l) => $l['posicao_por_mes'][$coluna], $linhas)), 2);
            $totais['vendas_por_mes'][$coluna] = round(array_sum(array_map(fn ($l) => (float) ($l['vendas_por_mes'][$coluna] ?? 0), $linhas)), 2);
        }

        // Mercado interno: o sistema ainda não tem as vendas de mercado
        // interno, então aqui só aparece o lado comprado.
        $interno = [
            'estoque_foto' => round($medido[$mi] + $estimado[$mi], 2),
            'entradas' => round($entradas[$mi], 2),
            'a_receber' => round($aReceber[$mi], 2),
        ];
        $interno['disponivel'] = round(array_sum($interno), 2);

        return [
            'foto' => $foto,
            'colunas' => $colunas,
            'linhas' => $linhas,
            'totais' => $totais,
            'interno' => $interno,
            'semPreparo' => $semPreparo,
            'avisos' => array_merge($avisos, $vendas['avisos']),
        ];
    }
}
