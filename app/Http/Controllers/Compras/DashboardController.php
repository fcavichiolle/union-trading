<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Classificacao;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

/**
 * "Shareable View" (Módulo 1, item 4): rota SOMENTE LEITURA, sem
 * nenhum formulário de edição. Por padrão exige login (não é pública),
 * mas o admin/setor de compras pode gerar um link assinado e temporário
 * (linkTemporario) para compartilhar com quem não tem conta no sistema
 * — sem precisar deixar a rota aberta ao público permanentemente.
 */
class DashboardController extends Controller
{
    /** Nomes dos filtros aceitos pelo relatório (GET e no link compartilhável). */
    private const FILTROS = ['mes_de', 'mes_ate', 'padrao', 'certificado', 'busca'];

    public function index(Request $request): View
    {
        return view('dashboard.compras', $this->dadosRelatorio($request));
    }

    /** Versão pública do relatório, acessível só com assinatura válida e não expirada. */
    public function publico(Request $request): View
    {
        return view('dashboard.compras-publico', $this->dadosRelatorio($request));
    }

    private function dadosRelatorio(Request $request): array
    {
        $filtros = [];
        foreach (self::FILTROS as $nome) {
            $filtros[$nome] = $request->string($nome)->toString();
        }

        $linhas = $this->distribuicao($filtros);
        $totalGeral = (float) $linhas->sum('total');

        return [
            'linhas' => $linhas,
            'totalGeral' => $totalGeral,
            'filtros' => $filtros,
        ];
    }

    /** Soma as sacas por peneira, agrupadas por padrão final, aplicando os filtros informados. */
    private function distribuicao(array $filtros)
    {
        return Classificacao::query()
            ->join('compras', 'compras.id', '=', 'classificacoes.compra_id')
            ->when($filtros['mes_de'] !== '', function ($q) use ($filtros) {
                $q->whereDate('compras.mes_ano', '>=', $filtros['mes_de'] . '-01');
            })
            ->when($filtros['mes_ate'] !== '', function ($q) use ($filtros) {
                $q->whereDate('compras.mes_ano', '<=', $filtros['mes_ate'] . '-01');
            })
            ->when($filtros['padrao'] !== '', function ($q) use ($filtros) {
                $q->where('classificacoes.padrao_final', $filtros['padrao']);
            })
            ->when($filtros['certificado'] !== '', function ($q) use ($filtros) {
                $q->where('compras.certificacao', $filtros['certificado']);
            })
            ->when($filtros['busca'] !== '', function ($q) use ($filtros) {
                $busca = $filtros['busca'];
                $q->join('fornecedores', 'fornecedores.id', '=', 'compras.fornecedor_id')
                    ->where(function ($sub) use ($busca) {
                        $sub->where('compras.uts', 'like', "%{$busca}%")
                            ->orWhere('fornecedores.nome', 'like', "%{$busca}%");
                    });
            })
            ->selectRaw('
                padrao_final,
                SUM(peneira_1718_sacas) as scs_1718,
                SUM(peneira_1416_sacas) as scs_1416,
                SUM(grinders_sacas) as grinders,
                SUM(mercado_interno_sacas) as mercado_interno,
                SUM(moka_sacas) as moka
            ')
            ->groupBy('padrao_final')
            ->get()
            ->map(function ($linha) {
                $linha->total = (float) $linha->scs_1718 + (float) $linha->scs_1416
                    + (float) $linha->grinders + (float) $linha->mercado_interno
                    + (float) $linha->moka;
                return $linha;
            });
    }

    /** Gera link assinado temporário (7 dias) para compartilhar sem exigir login, preservando os filtros aplicados. */
    public function linkTemporario(Request $request): RedirectResponse
    {
        $params = [];
        foreach (self::FILTROS as $nome) {
            $params[$nome] = $request->string($nome)->toString();
        }

        $url = URL::temporarySignedRoute('relatorio.publico', now()->addDays(7), $params);

        AuditLog::registrar('link_compartilhavel_gerado', $url, Auth::id());

        return back()->with('linkGerado', $url);
    }
}