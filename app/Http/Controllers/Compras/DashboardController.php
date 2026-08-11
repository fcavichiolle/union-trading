<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Classificacao;
use App\Models\Compra;
use App\Models\Entrega;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

/**
 * Tela de ESTOQUE (antiga "relatório de classificação"): distribuição das
 * sacas por armazém, padrão e peneira. Somente leitura, sem nenhum
 * formulário de edição.
 *
 * REGRA DO ESTOQUE: só entra definitivamente em estoque a compra que já
 * tem o **número do lote** informado pelo armazém (Compra::comNumeroLote()).
 * Enquanto o lote não chega, a compra fica em "aguardando" — e o volume
 * parado aparece como aviso na tela, nunca sumindo em silêncio: um estoque
 * que subnotifica sem avisar é pior do que não ter estoque nenhum.
 *
 * ATENÇÃO — isto é ENTRADA, não SALDO: o sistema ainda não registra saída
 * (embarque/faturamento dos contratos), então os totais dizem quanto
 * entrou em estoque, não quanto resta disponível para vender.
 *
 * Compartilhamento: admin/compras podem gerar um link assinado e temporário
 * (7 dias) para quem não tem conta. O **armazém fica de fora do link** — a
 * versão pública mostra a distribuição sem quebra por armazém e sem aceitar
 * esse filtro, para não expor onde o café está guardado nem entregar
 * números filtrados sem o destinatário saber.
 */
class DashboardController extends Controller
{
    /** Filtros da tela interna. */
    private const FILTROS = ['mes_de', 'mes_ate', 'padrao', 'certificado', 'armazem', 'situacao', 'busca'];

    /** Filtros que viajam no link compartilhável — sem 'armazem', de propósito. */
    private const FILTROS_LINK = ['mes_de', 'mes_ate', 'padrao', 'certificado', 'situacao', 'busca'];

    /** Situação da compra perante o estoque (código => rótulo). */
    public const SITUACOES = [
        'definitivo' => 'Estoque definitivo (com nº de lote)',
        'aguardando' => 'Aguardando nº do lote',
        'todos' => 'Todos',
    ];

    public const SITUACAO_PADRAO = 'definitivo';

    public function index(Request $request): View
    {
        return view('dashboard.compras', $this->dadosRelatorio($request, comArmazem: true));
    }

    /** Versão pública do estoque, acessível só com assinatura válida e não expirada. */
    public function publico(Request $request): View
    {
        return view('dashboard.compras-publico', $this->dadosRelatorio($request, comArmazem: false));
    }

    private function dadosRelatorio(Request $request, bool $comArmazem): array
    {
        $filtros = [];
        foreach (self::FILTROS as $nome) {
            $filtros[$nome] = $request->string($nome)->toString();
        }

        // A versão pública nunca quebra por armazém, mesmo que o parâmetro
        // apareça na URL.
        if (! $comArmazem) {
            $filtros['armazem'] = '';
        }

        if (! array_key_exists($filtros['situacao'], self::SITUACOES)) {
            $filtros['situacao'] = self::SITUACAO_PADRAO;
        }

        $linhas = $this->distribuicao($filtros, $comArmazem);
        $totalGeral = (float) $linhas->sum('total');

        return [
            'linhas' => $linhas,
            'totalGeral' => $totalGeral,
            'filtros' => $filtros,
            'comArmazem' => $comArmazem,
            'pendentes' => $comArmazem ? $this->volumesForaDoEstoque($filtros) : null,
        ];
    }

    /**
     * Soma as sacas por peneira, agrupadas por padrão final (e por armazém
     * na tela interna), aplicando os filtros informados.
     */
    private function distribuicao(array $filtros, bool $comArmazem)
    {
        // A classificação é da UTS inteira, mas o café pode ter entrado em
        // vários armazéns. O rateio abaixo distribui cada peneira entre as
        // entregas na proporção do volume de cada uma sobre o total
        // classificado — assim o total do estoque bate com o que realmente
        // entrou, repartido pelas proporções da classificação.
        // O "* 1.0" é obrigatório: no SQLite a divisão entre dois inteiros
        // trunca (250/500 = 0), o que zeraria o rateio silenciosamente.
        $fator = '(entregas.volume_sacas * 1.0 / NULLIF(cl.total_classificado, 0))';

        $query = Classificacao::query()
            ->join('compras', 'compras.id', '=', 'classificacoes.compra_id')
            ->join('entregas', 'entregas.compra_id', '=', 'compras.id')
            ->joinSub(
                Classificacao::selectRaw(
                    'compra_id, (peneira_1718_sacas + peneira_1416_sacas + mercado_interno_sacas'
                    . ' + grinders_sacas + moka_sacas) as total_classificado'
                ),
                'cl',
                'cl.compra_id',
                '=',
                'classificacoes.compra_id'
            )
            ->when($filtros['mes_de'] !== '', function ($q) use ($filtros) {
                // Recorte pelo mês da ENTRADA em armazém, que é o que
                // interessa para estoque.
                $q->whereDate('entregas.mes_ano', '>=', $filtros['mes_de'] . '-01');
            })
            ->when($filtros['mes_ate'] !== '', function ($q) use ($filtros) {
                $q->whereDate('entregas.mes_ano', '<=', $filtros['mes_ate'] . '-01');
            })
            ->when($filtros['padrao'] !== '', function ($q) use ($filtros) {
                $q->where('classificacoes.padrao_final', $filtros['padrao']);
            })
            ->when($filtros['certificado'] !== '', function ($q) use ($filtros) {
                $q->where('compras.certificacao', $filtros['certificado']);
            })
            ->when($filtros['armazem'] !== '', function ($q) use ($filtros) {
                $q->where('entregas.armazem', $filtros['armazem']);
            })
            ->when($filtros['busca'] !== '', function ($q) use ($filtros) {
                $busca = $filtros['busca'];
                $q->join('fornecedores', 'fornecedores.id', '=', 'compras.fornecedor_id')
                    ->where(function ($sub) use ($busca) {
                        $sub->where('compras.uts', 'like', "%{$busca}%")
                            ->orWhere('fornecedores.nome', 'like', "%{$busca}%");
                    });
            });

        $this->aplicarSituacao($query, $filtros['situacao']);

        $colunas = "padrao_final,
            SUM(peneira_1718_sacas * {$fator}) as scs_1718,
            SUM(peneira_1416_sacas * {$fator}) as scs_1416,
            SUM(grinders_sacas * {$fator}) as grinders,
            SUM(mercado_interno_sacas * {$fator}) as mercado_interno,
            SUM(moka_sacas * {$fator}) as moka";

        if ($comArmazem) {
            $query->selectRaw('entregas.armazem, ' . $colunas)->groupBy('entregas.armazem', 'padrao_final')
                ->orderBy('entregas.armazem')->orderBy('padrao_final');
        } else {
            $query->selectRaw($colunas)->groupBy('padrao_final');
        }

        return $query->get()->map(function ($linha) {
            $linha->total = (float) $linha->scs_1718 + (float) $linha->scs_1416
                + (float) $linha->grinders + (float) $linha->mercado_interno
                + (float) $linha->moka;

            return $linha;
        });
    }

    /** Recorte pelo número do lote — a regra que define o que é estoque. */
    private function aplicarSituacao($query, string $situacao): void
    {
        match ($situacao) {
            'definitivo' => $query->whereNotNull('entregas.numero_lote')->where('entregas.numero_lote', '!=', ''),
            'aguardando' => $query->where(fn ($q) => $q->whereNull('entregas.numero_lote')->orWhere('entregas.numero_lote', '=', '')),
            default => null, // 'todos'
        };
    }

    /**
     * Volumes que existem mas NÃO aparecem na tabela, para o estoque não
     * subnotificar em silêncio:
     *  - aguardando lote: comprado, ainda não é estoque definitivo;
     *  - com lote e sem classificação: já é estoque, mas sem distribuição
     *    de peneira lançada, então não tem linha na tabela.
     *
     * Usa o volume da COMPRA (não das peneiras) e ignora de propósito os
     * filtros 'padrao' e 'situacao' — são justamente os recortes que
     * escondem esses volumes.
     */
    private function volumesForaDoEstoque(array $filtros): array
    {
        // Agora o volume mora na ENTREGA, então as duas contas partem dela.
        $base = fn () => Entrega::query()
            ->join('compras', 'compras.id', '=', 'entregas.compra_id')
            ->when($filtros['mes_de'] !== '', fn ($q) => $q->whereDate('entregas.mes_ano', '>=', $filtros['mes_de'] . '-01'))
            ->when($filtros['mes_ate'] !== '', fn ($q) => $q->whereDate('entregas.mes_ano', '<=', $filtros['mes_ate'] . '-01'))
            ->when($filtros['certificado'] !== '', fn ($q) => $q->where('compras.certificacao', $filtros['certificado']))
            ->when($filtros['armazem'] !== '', fn ($q) => $q->where('entregas.armazem', $filtros['armazem']))
            ->when($filtros['busca'] !== '', function ($q) use ($filtros) {
                $busca = $filtros['busca'];
                $q->join('fornecedores', 'fornecedores.id', '=', 'compras.fornecedor_id')
                    ->where(function ($sub) use ($busca) {
                        $sub->where('compras.uts', 'like', "%{$busca}%")
                            ->orWhere('fornecedores.nome', 'like', "%{$busca}%");
                    });
            });

        $aguardando = $base()->semNumeroLote();
        $semClassificacao = $base()->comNumeroLote()
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('classificacoes')
                ->whereColumn('classificacoes.compra_id', 'compras.id'));

        // Clona antes de cada agregação: sum()/count() deixam bindings de
        // agregado no builder e reaproveitá-lo daria número errado.
        return [
            'aguardando_sacas' => (float) (clone $aguardando)->sum('entregas.volume_sacas'),
            'aguardando_compras' => (clone $aguardando)->count(),
            'sem_classificacao_sacas' => (float) (clone $semClassificacao)->sum('entregas.volume_sacas'),
            'sem_classificacao_compras' => (clone $semClassificacao)->count(),
        ];
    }

    /**
     * Gera link assinado temporário (7 dias) para compartilhar sem exigir
     * login, preservando os filtros — menos o armazém, que não sai da casa.
     */
    public function linkTemporario(Request $request): RedirectResponse
    {
        $params = [];
        foreach (self::FILTROS_LINK as $nome) {
            $params[$nome] = $request->string($nome)->toString();
        }

        $url = URL::temporarySignedRoute('relatorio.publico', now()->addDays(7), $params);

        AuditLog::registrar('link_compartilhavel_gerado', $url, Auth::id());

        return back()->with('linkGerado', $url);
    }
}
