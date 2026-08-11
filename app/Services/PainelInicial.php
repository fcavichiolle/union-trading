<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\Contrato;
use App\Models\Entrega;
use App\Models\Fixacao;
use App\Models\Fornecedor;
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
            'entregas_sem_lote' => Entrega::semNumeroLote()->count(),
            'compras_sem_classificacao' => Compra::whereDoesntHave('classificacao')->count(),
            'compras_sem_preco' => Compra::semPreco()->count(),
            'compras_com_saldo' => Compra::comSaldoAEntregar()->count(),
            'fornecedores_sem_documento' => Fornecedor::semDocumento()->count(),
            'compras_com_pendencia' => Compra::comPendencia()->count(),

            'contratos_a_fixar' => $aFixar->count(),
            'lotes_a_fixar' => (int) $aFixar->sum(fn (Contrato $c) => $c->lotesRestantes()),
            'contratos_sem_embarque' => Contrato::ativos()->whereNull('embarque_mes')->count(),

            // "Comprado" é o que realmente entrou no armazém, não o
            // contratado — é o número que casa com o estoque.
            'sacas_compradas' => (float) Entrega::sum('volume_sacas'),
            'sacas_contratadas_compra' => (float) Compra::sum('volume_contratado'),
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
                'quantidade' => $n['entregas_sem_lote'],
                'titulo' => 'entregas sem nº do lote',
                'descricao' => 'Entradas em armazém que ainda não contam como estoque definitivo.',
                'url' => route('compras.index', ['pendencia' => 'sem_lote']),
                'tom' => 'alerta',
                'perfis' => ['admin', 'compras'],
            ],
            [
                'quantidade' => $n['compras_com_saldo'],
                'titulo' => 'UTS com saldo a entregar',
                'descricao' => 'Volume contratado que ainda não entrou no armazém — liquide a compra se não vem mais.',
                'url' => route('compras.index', ['pendencia' => 'saldo_a_entregar']),
                'tom' => 'atencao',
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
                'quantidade' => $n['compras_sem_preco'],
                'titulo' => 'sem preço',
                'descricao' => 'Compras sem valor da saca lançado.',
                'url' => route('compras.index', ['pendencia' => 'sem_preco']),
                'tom' => 'atencao',
                'perfis' => ['admin', 'compras', 'financeiro'],
            ],
            [
                'quantidade' => $n['fornecedores_sem_documento'],
                'titulo' => 'vendedores a confirmar',
                'descricao' => 'Fornecedores cadastrados sem CNPJ/CPF informado.',
                'url' => route('compras.index', ['pendencia' => 'sem_documento']),
                'tom' => 'neutro',
                'perfis' => ['admin', 'compras'],
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
        return Compra::with(['fornecedor', 'classificacao'])
            ->withSum('entregas as sacas_entregues', 'volume_sacas')
            ->latest('data_compra')->latest('id')
            ->limit($limite)
            ->get();
    }

    public function ultimosContratos(int $limite = 5)
    {
        return Contrato::withSum('fixacoes as lotes_fixados', 'lotes')
            ->orderByDesc('data_contrato')->orderByDesc('id')
            ->limit($limite)
            ->get();
    }
}
