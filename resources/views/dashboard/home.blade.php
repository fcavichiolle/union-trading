@extends('layouts.app')

@section('title', 'Início')
@section('subtitle', 'Bem-vindo, ' . $user->name . '.')

@section('content')
    {{-- 1. O que falta fazer — cards só aparecem quando existe pendência --}}
    @if (count($pendencias))
        <h2 class="painel-titulo">O que falta fazer</h2>
        <div class="pend-grid">
            @foreach ($pendencias as $p)
                <a href="{{ $p['url'] }}" class="pend-card pend-card--{{ $p['tom'] }}">
                    <span class="pend-card__num">{{ $p['quantidade'] }}</span>
                    <span class="pend-card__titulo">{{ $p['titulo'] }}</span>
                    <span class="pend-card__desc">{{ $p['descricao'] }}</span>
                </a>
            @endforeach
        </div>
    @else
        <div class="painel-ok">
            <span class="painel-ok__dot"></span>
            <div>
                <strong>Tudo em dia.</strong>
                <span>Nenhuma compra ou contrato com etapa pendente.</span>
            </div>
        </div>
    @endif

    {{-- 2. Posição geral --}}
    <h2 class="painel-titulo">Posição geral</h2>
    <div class="num-grid">
        <div class="num-tile">
            <span class="num-tile__lbl">Sacas compradas</span>
            <span class="num-tile__val">{{ number_format($numeros['sacas_compradas'], 0, ',', '.') }}</span>
            <span class="num-tile__sub">em compras lançadas</span>
        </div>
        <div class="num-tile">
            <span class="num-tile__lbl">Sacas em contrato</span>
            <span class="num-tile__val">{{ number_format($numeros['sacas_contratadas'], 0, ',', '.') }}</span>
            <span class="num-tile__sub">{{ $numeros['contratos_total'] }} contrato(s) de exportação</span>
        </div>
        <div class="num-tile">
            <span class="num-tile__lbl">Saldo</span>
            @php $saldo = $numeros['sacas_compradas'] - $numeros['sacas_contratadas']; @endphp
            <span class="num-tile__val {{ $saldo < 0 ? 'is-neg' : '' }}">{{ number_format($saldo, 0, ',', '.') }}</span>
            <span class="num-tile__sub">compras − contratos</span>
        </div>
        <div class="num-tile">
            <span class="num-tile__lbl">Lotes a fixar</span>
            <span class="num-tile__val {{ $numeros['lotes_a_fixar'] > 0 ? 'is-alerta' : '' }}">{{ $numeros['lotes_a_fixar'] }}</span>
            <span class="num-tile__sub">{{ $numeros['lotes_fixados'] }} lote(s) já fixados</span>
        </div>
    </div>
    <p class="painel-nota">
        Totais gerais de tudo que está lançado no sistema — sem recorte de safra ou mês, e sem
        considerar estoque anterior ao sistema. Para cortes por período use o
        <a href="{{ route('relatorio.index') }}">Estoque</a> ou os filtros de
        <a href="{{ route('compras.index') }}">Compras lançadas</a>.
    </p>

    {{-- 3. Últimos lançamentos --}}
    @if ($ultimasCompras->isNotEmpty() || $ultimosContratos->isNotEmpty())
        <div class="contract-cols" style="margin-top:26px;">
            @if ($ultimasCompras->isNotEmpty())
                <div class="card">
                    <div class="card__header">
                        <h2>Últimas compras</h2>
                    </div>
                    <div class="card__body" style="padding:0;">
                        <div class="table-wrap" style="border:0; border-radius:0;">
                            <table class="data">
                                <thead>
                                    <tr>
                                        <th>UTS</th>
                                        <th>Fornecedor</th>
                                        <th class="num">Entregue / contratado</th>
                                        <th>Pendência</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ultimasCompras as $c)
                                        <tr>
                                            <td><a href="{{ route('compras.show', $c) }}"><strong>{{ $c->uts }}</strong></a></td>
                                            <td>{{ \Illuminate\Support\Str::limit($c->fornecedor?->nome, 20) }}</td>
                                            <td class="num">
                                                {{ number_format($c->sacasEntregues(), 0, ',', '.') }} /
                                                {{ number_format((float) $c->volume_contratado, 0, ',', '.') }}
                                            </td>
                                            <td>
                                                @if ($c->saldoAEntregar() > 0.01)
                                                    <span class="badge badge--amber">a entregar</span>
                                                @elseif (! $c->classificacao)
                                                    <span class="badge badge--amber">classificar</span>
                                                @elseif ($c->valor_saca === null)
                                                    <span class="badge badge--amber">preço</span>
                                                @else
                                                    <span class="badge badge--green">completa</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            @if ($ultimosContratos->isNotEmpty())
                <div class="card">
                    <div class="card__header">
                        <h2>Últimos contratos</h2>
                    </div>
                    <div class="card__body" style="padding:0;">
                        <div class="table-wrap" style="border:0; border-radius:0;">
                            <table class="data">
                                <thead>
                                    <tr>
                                        <th>UT</th>
                                        <th>Comprador</th>
                                        <th class="num">Lotes</th>
                                        <th>Preço</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ultimosContratos as $ct)
                                        <tr>
                                            <td><a href="{{ route('contratos.show', $ct) }}"><strong>UT {{ $ct->numero_ut }}</strong></a></td>
                                            <td>{{ \Illuminate\Support\Str::limit($ct->cliente_nome, 20) }}</td>
                                            <td class="num">{{ $ct->lotes }}</td>
                                            <td>
                                                @if ($ct->fixado)
                                                    <span class="badge badge--green">FIXED</span>
                                                @elseif ($ct->parcialmenteFixado())
                                                    <span class="badge badge--amber">PARCIAL {{ $ct->lotesFixados() }}/{{ $ct->lotes }}</span>
                                                @else
                                                    <span class="badge badge--muted">A FIXAR</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="home-foot">
        <span>Union Trading · Sistema de compras e classificação</span>
        <span class="mono">São Paulo, BR</span>
    </div>
@endsection
