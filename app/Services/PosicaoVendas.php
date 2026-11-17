<?php

namespace App\Services;

use App\Models\Contrato;
use Illuminate\Support\Carbon;

/**
 * Vendas em aberto por mês × standard — o lado "vendido" do Long & Short.
 *
 * Na planilha isso era o bloco de SUMIFS no fim da aba VENDAS-FOB, e a
 * venda só saía da conta quando alguém apagava a linha à mão.
 *
 * A regra aqui: a venda sai da posição na BAIXA do estoque, não na nota.
 * Faturar e dar baixa são coisas diferentes — o café faturado e ainda não
 * baixado está no armazém e conta no estoque físico (Digisul); se a venda
 * dele sumisse na nota, a posição ficaria mais comprada do que é.
 *
 * O que entra:
 *  - cada embarque ainda sem baixa, no mês de faturamento DELE;
 *  - o saldo do contrato que ainda não virou embarque, no mês de embarque
 *    do contrato — senão a venda recém-lançada, sem logística nenhuma,
 *    ficaria fora da posição justamente quando mais importa.
 *
 * Contrato cancelado não entra. Contrato sem standard ou sem mês não some:
 * vai para uma coluna/linha própria e para a lista de avisos.
 */
final class PosicaoVendas
{
    /** Quantos meses a grade mostra, a partir do mês corrente. */
    public const MESES = 12;

    public const SEM_STANDARD = 'SEM_STANDARD';
    public const ANTERIORES = 'ANTERIORES';
    public const POSTERIORES = 'POSTERIORES';
    public const SEM_MES = 'SEM_MES';

    /**
     * @return array{
     *     colunas: array<string, string>,
     *     linhas: array<string, array{rotulo: string, valores: array<string, float>, total: float}>,
     *     totais: array<string, float>,
     *     total: float,
     *     avisos: array<int, string>
     * }
     */
    public function calcular(): array
    {
        $inicio = now()->startOfMonth();
        $meses = [];
        for ($i = 0; $i < self::MESES; $i++) {
            $m = $inicio->copy()->addMonths($i);
            $meses[$m->format('Y-m')] = self::rotuloDoMes($m);
        }
        $ultimo = array_key_last($meses);

        $soma = [];           // [standard][coluna] => sacas
        $semStandard = [];
        $semMes = [];

        $contratos = Contrato::ativos()->with('embarques')->get();

        foreach ($contratos as $contrato) {
            $standard = $contrato->standard ?: self::SEM_STANDARD;

            $itens = [];
            foreach ($contrato->embarques as $embarque) {
                if (! $embarque->baixado()) {
                    $itens[] = [$embarque->mes_faturamento, (float) $embarque->sacas];
                }
            }

            // Menos de uma saca de sobra é arredondamento de peso, não venda.
            $aProgramar = $contrato->sacasAProgramar();
            if ($aProgramar >= 1) {
                $itens[] = [$contrato->embarque_mes, $aProgramar];
            }

            foreach ($itens as [$mes, $sacas]) {
                $coluna = $this->colunaDoMes($mes, $inicio, $ultimo);
                $soma[$standard][$coluna] = ($soma[$standard][$coluna] ?? 0) + $sacas;

                if ($coluna === self::SEM_MES) {
                    $semMes[$contrato->numero_ut] = true;
                }
            }

            if ($itens !== [] && $standard === self::SEM_STANDARD) {
                $semStandard[$contrato->numero_ut] = true;
            }
        }

        // Colunas extras só aparecem quando têm volume — coluna vazia fixa
        // na tela vira ruído e para de ser lida.
        $colunas = [];
        if ($this->temVolume($soma, self::ANTERIORES)) {
            $colunas[self::ANTERIORES] = 'Atrasado';
        }
        $colunas += $meses;
        if ($this->temVolume($soma, self::POSTERIORES)) {
            $colunas[self::POSTERIORES] = 'Depois';
        }
        if ($this->temVolume($soma, self::SEM_MES)) {
            $colunas[self::SEM_MES] = 'Sem mês';
        }

        $rotulos = Contrato::standards();
        if (isset($soma[self::SEM_STANDARD])) {
            $rotulos[self::SEM_STANDARD] = 'Sem standard';
        }

        $linhas = [];
        $totais = array_fill_keys(array_keys($colunas), 0.0);

        foreach ($rotulos as $codigo => $rotulo) {
            $valores = [];
            foreach (array_keys($colunas) as $coluna) {
                $valores[$coluna] = round($soma[$codigo][$coluna] ?? 0, 2);
                $totais[$coluna] += $valores[$coluna];
            }
            $linhas[$codigo] = ['rotulo' => $rotulo, 'valores' => $valores, 'total' => round(array_sum($valores), 2)];
        }

        $avisos = [];
        if ($semStandard !== []) {
            $avisos[] = 'Sem standard (não entram em nenhuma linha da posição): UT '
                . implode(', UT ', array_keys($semStandard)) . '.';
        }
        if ($semMes !== []) {
            $avisos[] = 'Sem mês de embarque definido: UT ' . implode(', UT ', array_keys($semMes)) . '.';
        }

        return [
            'colunas' => $colunas,
            'linhas' => $linhas,
            'totais' => array_map(fn ($v) => round($v, 2), $totais),
            'total' => round(array_sum($totais), 2),
            'avisos' => $avisos,
        ];
    }

    private function colunaDoMes(mixed $mes, Carbon $inicio, string $ultimo): string
    {
        if (! $mes) {
            return self::SEM_MES;
        }

        $chave = Carbon::parse($mes)->format('Y-m');

        return match (true) {
            $chave < $inicio->format('Y-m') => self::ANTERIORES,
            $chave > $ultimo => self::POSTERIORES,
            default => $chave,
        };
    }

    /** @param array<string, array<string, float>> $soma */
    private function temVolume(array $soma, string $coluna): bool
    {
        foreach ($soma as $porColuna) {
            if (($porColuna[$coluna] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /** "Nov/26" — curto, porque são doze colunas lado a lado. */
    public static function rotuloDoMes(Carbon $mes): string
    {
        $abrev = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        return $abrev[$mes->month - 1] . '/' . $mes->format('y');
    }
}
