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
        $mes = $request->string('mes')->toString(); // "YYYY-MM" vindo de <input type="month">

        $linhas = $this->distribuicao($mes, 'padrao_final', 'padrao_final');
        $linhasCertificacao = $this->distribuicao($mes, 'compras.certificacao', 'certificacao');

        // O total geral é o mesmo nas duas tabelas (mesma soma de sacas).
        $totalGeral = (float) $linhas->sum('total');

        return [
            'linhas' => $linhas,
            'linhasCertificacao' => $linhasCertificacao,
            'totalGeral' => $totalGeral,
            'mesFiltro' => $mes,
        ];
    }

    /**
     * Soma as sacas por peneira, agrupando pela coluna informada.
     * $coluna/$alias são strings FIXAS definidas aqui no código (nunca
     * vêm do usuário), então não há risco de injeção no selectRaw.
     */
    private function distribuicao(string $mes, string $coluna, string $alias)
    {
        return Classificacao::query()
            ->join('compras', 'compras.id', '=', 'classificacoes.compra_id')
            ->when($mes !== '', function ($q) use ($mes) {
                [$ano, $mesNum] = explode('-', $mes);
                $q->whereYear('compras.mes_ano', $ano)->whereMonth('compras.mes_ano', $mesNum);
            })
            ->selectRaw("
                {$coluna} as {$alias},
                SUM(peneira_1718_sacas) as scs_1718,
                SUM(peneira_1416_sacas) as scs_1416,
                SUM(grinders_sacas) as grinders,
                SUM(mercado_interno_sacas) as mercado_interno
            ")
            ->groupBy($coluna)
            ->get()
            ->map(function ($linha) {
                $linha->total = (float) $linha->scs_1718 + (float) $linha->scs_1416
                    + (float) $linha->grinders + (float) $linha->mercado_interno;
                return $linha;
            });
    }

    /** Gera link assinado temporário (7 dias) para compartilhar sem exigir login. */
    public function linkTemporario(Request $request): RedirectResponse
    {
        $url = URL::temporarySignedRoute(
            'relatorio.publico',
            now()->addDays(7),
            ['mes' => $request->string('mes')->toString()]
        );

        AuditLog::registrar('link_compartilhavel_gerado', $url, Auth::id());

        return back()->with('linkGerado', $url);
    }
}