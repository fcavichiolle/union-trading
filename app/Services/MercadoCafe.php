<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cotações de café (arábica NY / robusta Londres) e câmbio via Yahoo
 * Finance (endpoint público v8/chart — dados com delay de ~15 min).
 *
 * Estratégia de robustez:
 *  - snapshot() é cacheado por 30s (não martelar o Yahoo a cada visita);
 *  - cada símbolo que responde OK é gravado num cache de longa duração
 *    ("último valor conhecido"); se o Yahoo falhar/não tiver a posição,
 *    devolvemos esse último valor marcado com stale=true;
 *  - posição sem nenhum dado (nem histórico) sai com price=null e o
 *    front-end mostra "indisponível".
 *
 * Os tickers do Yahoo variam de posição para posição (e a cobertura do
 * robusta de Londres é irregular) — para ajustar, basta editar as listas
 * ARABICA/ROBUSTA abaixo.
 */
class MercadoCafe
{
    private const CACHE_SNAPSHOT = 'mercado.snapshot';
    private const CACHE_TTL = 30; // segundos

    /** Posições do arábica (ICE NY). code = como a mesa chama; symbol = ticker Yahoo. */
    public const ARABICA = [
        ['code' => 'KCU6', 'month' => 'Set 2026', 'symbol' => 'KCU26.NYB'],
        ['code' => 'KCZ6', 'month' => 'Dez 2026', 'symbol' => 'KCZ26.NYB'],
        ['code' => 'KCH7', 'month' => 'Mar 2027', 'symbol' => 'KCH27.NYB'],
        ['code' => 'KCK7', 'month' => 'Mai 2027', 'symbol' => 'KCK27.NYB'],
        ['code' => 'KCN7', 'month' => 'Jul 2027', 'symbol' => 'KCN27.NYB'],
        ['code' => 'KCU7', 'month' => 'Set 2027', 'symbol' => 'KCU27.NYB'],
        ['code' => 'KCZ7', 'month' => 'Dez 2027', 'symbol' => 'KCZ27.NYB'],
    ];

    /**
     * Posições do robusta (ICE Londres). Obs.: Londres NÃO tem contrato de
     * dezembro — os meses são F/H/K/N/U/X (X = novembro).
     */
    public const ROBUSTA = [
        ['code' => 'RCU26', 'month' => 'Set 2026', 'symbol' => 'RCU26.NYB'],
        ['code' => 'RCX26', 'month' => 'Nov 2026', 'symbol' => 'RCX26.NYB'],
        ['code' => 'RCF27', 'month' => 'Jan 2027', 'symbol' => 'RCF27.NYB'],
        ['code' => 'RCH27', 'month' => 'Mar 2027', 'symbol' => 'RCH27.NYB'],
    ];

    /** Câmbio: chave do JSON => ticker Yahoo. */
    public const CAMBIO = [
        'dolar' => 'BRL=X',
        'euro' => 'EURBRL=X',
    ];

    /** Snapshot completo no formato consumido pelo front-end (cache 30s). */
    public function snapshot(): array
    {
        return Cache::remember(self::CACHE_SNAPSHOT, self::CACHE_TTL, fn () => $this->montarSnapshot());
    }

    private function montarSnapshot(): array
    {
        $simbolos = array_values(array_unique(array_merge(
            array_values(self::CAMBIO),
            array_column(self::ARABICA, 'symbol'),
            array_column(self::ROBUSTA, 'symbol'),
        )));

        $cotacoes = $this->buscarCotacoes($simbolos);

        $montarLista = function (array $posicoes) use ($cotacoes): array {
            return array_map(function (array $p) use ($cotacoes) {
                $c = $cotacoes[$p['symbol']] ?? null;

                return [
                    'code' => $p['code'],
                    'month' => $p['month'],
                    'price' => $c['price'] ?? null,
                    'dif' => $c['dif'] ?? null,
                    'max' => $c['max'] ?? null,
                    'min' => $c['min'] ?? null,
                    'open' => $c['open'] ?? null,
                    'close' => $c['close'] ?? null,
                    'stale' => $c['stale'] ?? false,
                ];
            }, $posicoes);
        };

        $cambio = [];
        foreach (self::CAMBIO as $chave => $symbol) {
            $c = $cotacoes[$symbol] ?? null;
            $cambio[$chave] = [
                'value' => $c['price'] ?? null,
                'dif' => $c['dif'] ?? null,
                'stale' => $c['stale'] ?? false,
            ];
        }

        return [
            'updated_at' => now()->toIso8601String(),
            'cambio' => $cambio,
            'arabica' => $montarLista(self::ARABICA),
            'robusta' => $montarLista(self::ROBUSTA),
        ];
    }

    /**
     * Busca todos os símbolos em paralelo. Falhou/indisponível => cai no
     * último valor conhecido (stale=true) ou em null.
     *
     * @return array<string, array|null> symbol => cotação
     */
    private function buscarCotacoes(array $simbolos): array
    {
        try {
            $respostas = Http::pool(fn (Pool $pool) => array_map(
                fn (string $s) => $pool->as($s)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'])
                    ->timeout(8)
                    ->get('https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode($s), [
                        'interval' => '1d',
                        'range' => '1d',
                    ]),
                $simbolos
            ));
        } catch (\Throwable) {
            $respostas = [];
        }

        $cotacoes = [];
        foreach ($simbolos as $s) {
            $resposta = $respostas[$s] ?? null;
            $dados = $resposta instanceof Response && $resposta->successful()
                ? $this->extrairCotacao($resposta->json())
                : null;

            if ($dados !== null) {
                Cache::forever("mercado.last.{$s}", $dados);
                $cotacoes[$s] = $dados + ['stale' => false];
            } else {
                $ultimo = Cache::get("mercado.last.{$s}");
                $cotacoes[$s] = $ultimo !== null ? $ultimo + ['stale' => true] : null;
            }
        }

        return $cotacoes;
    }

    /** Extrai preço/variação/OHLC do JSON do endpoint v8/finance/chart. */
    private function extrairCotacao(?array $json): ?array
    {
        $result = $json['chart']['result'][0] ?? null;
        $meta = $result['meta'] ?? null;

        if (! is_array($meta) || ! isset($meta['regularMarketPrice'])) {
            return null;
        }

        $quote = $result['indicators']['quote'][0] ?? [];
        $price = (float) $meta['regularMarketPrice'];
        $close = $meta['chartPreviousClose'] ?? $meta['previousClose'] ?? null;
        $close = $close !== null ? (float) $close : null;

        return [
            'price' => $price,
            'dif' => $close !== null ? round($price - $close, 4) : null,
            'max' => $this->ultimoValor($quote['high'] ?? []) ?? $this->numeroOuNull($meta['regularMarketDayHigh'] ?? null),
            'min' => $this->ultimoValor($quote['low'] ?? []) ?? $this->numeroOuNull($meta['regularMarketDayLow'] ?? null),
            'open' => $this->ultimoValor($quote['open'] ?? []),
            'close' => $close,
        ];
    }

    /** Último valor não nulo de uma série do Yahoo (as séries vêm com null nos buracos). */
    private function ultimoValor(array $serie): ?float
    {
        for ($i = count($serie) - 1; $i >= 0; $i--) {
            if ($serie[$i] !== null) {
                return (float) $serie[$i];
            }
        }

        return null;
    }

    private function numeroOuNull(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }
}
