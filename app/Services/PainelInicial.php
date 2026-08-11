<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\Contrato;
use App\Models\Fixacao;
use App\Models\User;

/**
 * Números do painel inicial (tela "Início") e dos badges do menu lateral.
 *
 * A ideia da tela é responder "o que falta fazer agora?" — por isso as
 * pendências só entram na lista quando existem (contador zerado não vira
 * card). Os totais de posição são gerais, sem recorte de período: quem
 * precisa de corte por mês usa o Relatório ou "Compras lançadas".
 *
 * Registrado como singleton (AppServiceProvider): o layout (badges) e a
 * home pedem os mesmos números na mesma requisição e as queries rodam
 * uma vez só.
 */
class PainelInicial
{
    /** @var array<string, int|float>|null */
    private ?array $numeros = null;

    /** @return array<string, int|float> */
    public function numeros(): array
    {
        if ($this->numeros !== null) {
            return $this->numeros;
        }

        // Contratos ainda não fixados, com o total de lotes já fixado junto
        // (o saldo por contrato é lotes - fixados, ver Contrato::lotesRestantes()).
        // Cancelados ficam de fora de tudo: não são mais posição.
        $aFixar = Contrato::withSum('fixacoes as lotes_fixados', 'lotes')
            ->ativos()
            ->where('fixado', false)
            ->get();

        return $this->numeros = [
            'compras_sem_lote' => Compra::semNumeroLote()->count(),
            'compras_sem_classificacao' => Compra::whereDoesntHave('classificacao')->count(),
            'compras_sem_financeiro' => Compra::whereDoesntHave('financeiro')->count(),
            'compras_com_pendencia' => Compra::comPendencia()->count(),

            'contratos_a_fixar' => $aFixar->count(),
            'lotes_a_fixar' => (int) $aFixar->sum(fn (Contrato $c) => $c->lotesRestantes()),
            'contratos_sem_embarque' => Contrato::ativos()->whereNull('embarque_mes')->count(),

            'sacas_compradas' => (float) Compra::sum('volume_sacas'),
            'sacas_contratadas' => (float) Contrato::ativos()->sum('sacas'),
            'contratos_total' => Contrato::ativos()->count(),
            'lotes_fixados' => (int) Fixacao::sum('lotes'),
        ];
    }

    /**
     * Cards de pendência visíveis para este usuário, já em ordem de
     * urgência e sem os zerados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendencias(User $user): array
    {
        $n = $this->numeros();

        $todas = [
            [
                'quantidade' => $n['compras_sem_lote'],
                'titulo' => 'sem nº do lote',
                'descricao' => 'Compras que ainda não contam como estoque definitivo.',
                'url' => route('compras.index', ['pendencia' => 'sem_lote']),
                'tom' => 'alerta',
                'perfis' => ['admin', 'compras'],
            ],
            [
                'quantidade' => $n['compras_sem_classificacao'],
                'titulo' => 'não classificadas',
                'descricao' => 'Compras sem distribuição de peneiras lançada.',
                'url' => route('compras.index', ['pendencia' => 'sem_classificacao']),
                'tom' => 'atencao',
                'perfis' => ['admin', 'compras'],
            ],
            [
                'quantidade' => $n['compras_sem_financeiro'],
                'titulo' => 'sem financeiro',
                'descricao' => 'Compras sem valor da saca lançado.',
                'url' => route('compras.index', ['pendencia' => 'sem_financeiro']),
                'tom' => 'atencao',
                'perfis' => ['admin', 'compras', 'financeiro'],
            ],
            [
                'quantidade' => $n['contratos_a_fixar'],
                'titulo' => 'contratos a fixar',
                'descricao' => $n['lotes_a_fixar'] . ' lote(s) de exposição em aberto.',
                'url' => route('ny.index'),
                'tom' => 'atencao',
                'perfis' => ['admin', 'compras'],
            ],
            [
                'quantidade' => $n['contratos_sem_embarque'],
                'titulo' => 'sem mês de embarque',
                'descricao' => 'Contratos sem a janela de embarque definida.',
                'url' => route('contratos.index'),
                'tom' => 'neutro',
                'perfis' => ['admin', 'compras'],
            ],
        ];

        return array_values(array_filter(
            $todas,
            fn (array $p) => $p['quantidade'] > 0 && $user->hasRole(...$p['perfis'])
        ));
    }

    /** Badges do menu lateral: rota => contagem (só quando > 0). */
    public function badgesMenu(User $user): array
    {
        $n = $this->numeros();
        $badges = [];

        if ($user->hasRole('admin', 'compras') && $n['compras_com_pendencia'] > 0) {
            $badges['compras.index'] = $n['compras_com_pendencia'];
        }

        if ($user->hasRole('admin', 'compras') && $n['lotes_a_fixar'] > 0) {
            $badges['ny.index'] = $n['lotes_a_fixar'];
        }

        return $badges;
    }

    /** Últimos lançamentos, para dar noção de atividade sem abrir as listas. */
    public function ultimasCompras(int $limite = 5)
    {
        return Compra::with('fornecedor')->latest()->limit($limite)->get();
    }

    public function ultimosContratos(int $limite = 5)
    {
        return Contrato::withSum('fixacoes as lotes_fixados', 'lotes')
            ->orderByDesc('data_contrato')->orderByDesc('id')
            ->limit($limite)
            ->get();
    }
}
