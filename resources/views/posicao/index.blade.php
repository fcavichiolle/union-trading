@extends('layouts.app')

@section('title', 'Posição Long & Short')
@section('subtitle', 'Quanto café há (e vai haver) de cada padrão contra o que está vendido. Positivo é long (sobra café), negativo é short (falta comprar).')

@section('crumb')
    <span>Mercado</span><span class="sep">/</span><b>Posição Long &amp; Short</b>
@endsection

@php
    $n = fn ($v) => number_format((float) $v, 0, ',', '.');
    // Zero vira traço: numa grade de doze meses, zero repetido é ruído.
    $cel = fn ($v) => abs((float) $v) < 0.5 ? '—' : $n($v);
    $sinal = fn ($v) => (float) $v <= -0.5 ? 'ls-neg' : ((float) $v >= 0.5 ? 'ls-pos' : '');
    $foto = $p['foto'];
    $t = $p['totais'];
@endphp

@section('content')
    @foreach ($p['avisos'] as $aviso)
        <div class="alert alert-error" style="margin-bottom:14px;">⚠ {{ $aviso }}</div>
    @endforeach

    <div class="num-grid" style="margin-bottom:22px;">
        <div class="num-tile">
            <span class="num-tile__lbl">Estoque hoje</span>
            <span class="num-tile__val">{{ $n($t['estoque_hoje']) }}</span>
            <span class="num-tile__sub">
                @if ($foto) foto de {{ $foto->data_foto->format('d/m/Y') }} + entregas − baixas @else sem foto do estoque @endif
            </span>
        </div>
        <div class="num-tile">
            <span class="num-tile__lbl">Compras a receber</span>
            <span class="num-tile__val">{{ $n($t['a_receber']) }}</span>
            <span class="num-tile__sub">saldo das UTS em aberto</span>
        </div>
        <div class="num-tile">
            <span class="num-tile__lbl">Vendas em aberto</span>
            <span class="num-tile__val">{{ $n($t['vendas']) }}</span>
            <span class="num-tile__sub">embarques sem baixa + a programar</span>
        </div>
        <div class="num-tile">
            <span class="num-tile__lbl">Posição</span>
            <span class="num-tile__val {{ $t['posicao'] < 0 ? 'is-neg' : '' }}">{{ $t['posicao'] > 0 ? '+' : '' }}{{ $n($t['posicao']) }}</span>
            <span class="num-tile__sub">{{ $t['posicao'] < 0 ? 'short — falta café para o que está vendido' : 'long — sobra café' }}</span>
        </div>
    </div>

    {{-- A grade da aba POSIÇAO_LONG SHORT: o saldo de cada padrão depois de
         atender as vendas de cada mês, acumulado. --}}
    <div class="card" style="margin-bottom:8px;">
        <div class="card__header card__header--dark mesh-texture">
            <h2>Posição mês a mês <span style="font-weight:400; opacity:.8;">(sacas, acumulado)</span></h2>
        </div>
        <div class="card__body" style="padding:0;">
            <div class="table-wrap" style="border:0; border-radius:0;">
                <table class="data ls-tabela">
                    <thead>
                        <tr>
                            <th>Standard</th>
                            <th class="num">Disponível</th>
                            @foreach ($p['colunas'] as $rotulo)
                                <th class="num">{{ $rotulo }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($p['linhas'] as $linha)
                            <tr>
                                <td><strong>{{ $linha['rotulo'] }}</strong></td>
                                <td class="num">{{ $cel($linha['disponivel']) }}</td>
                                @foreach ($linha['posicao_por_mes'] as $valor)
                                    <td class="num {{ $sinal($valor) }}">{{ $cel($valor) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="num"><strong>{{ $n($t['disponivel']) }}</strong></td>
                            @foreach ($t['posicao_por_mes'] ?? [] as $valor)
                                <td class="num {{ $sinal($valor) }}"><strong>{{ $cel($valor) }}</strong></td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <p style="margin:0 0 22px; font-size:12.5px; color:var(--muted);">
        Cada coluna é o que sobra (ou falta) do disponível depois das vendas até aquele mês. A última coluna é a posição final.
        Venda atrasada pesa já no primeiro mês.
    </p>

    {{-- De onde vem cada número: a conta aberta, padrão por padrão. --}}
    <div class="card" style="margin-bottom:8px;">
        <div class="card__header"><h2>Como a posição é formada</h2></div>
        <div class="card__body" style="padding:0;">
            <div class="table-wrap" style="border:0; border-radius:0;">
                <table class="data ls-tabela">
                    <thead>
                        <tr>
                            <th>Standard</th>
                            <th class="num" title="Lotes já preparados neste padrão, na foto do estoque">Medido</th>
                            <th class="num" title="Rendimento estimado da bica e do reaproveitamento que estão na foto">+ Bica (est.)</th>
                            <th class="num" title="Entregas lançadas a partir da data da foto">+ Entregas</th>
                            <th class="num" title="Embarques com baixa a partir da data da foto">− Baixas</th>
                            <th class="num">= Estoque hoje</th>
                            <th class="num" title="Saldo a entregar das compras em aberto">+ A receber</th>
                            <th class="num">= Disponível</th>
                            <th class="num">− Vendas</th>
                            <th class="num">= Posição</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($p['linhas'] as $linha)
                            <tr>
                                <td><strong>{{ $linha['rotulo'] }}</strong></td>
                                <td class="num">{{ $cel($linha['medido']) }}</td>
                                <td class="num">{{ $cel($linha['estimado']) }}</td>
                                <td class="num">{{ $cel($linha['entradas']) }}</td>
                                <td class="num">{{ $cel($linha['baixas']) }}</td>
                                <td class="num"><strong>{{ $cel($linha['estoque_hoje']) }}</strong></td>
                                <td class="num">{{ $cel($linha['a_receber']) }}</td>
                                <td class="num"><strong>{{ $cel($linha['disponivel']) }}</strong></td>
                                <td class="num">{{ $cel($linha['vendas']) }}</td>
                                <td class="num {{ $sinal($linha['posicao']) }}"><strong>{{ $cel($linha['posicao']) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="num"><strong>{{ $n($t['medido']) }}</strong></td>
                            <td class="num"><strong>{{ $n($t['estimado']) }}</strong></td>
                            <td class="num"><strong>{{ $n($t['entradas']) }}</strong></td>
                            <td class="num"><strong>{{ $n($t['baixas']) }}</strong></td>
                            <td class="num"><strong>{{ $n($t['estoque_hoje']) }}</strong></td>
                            <td class="num"><strong>{{ $n($t['a_receber']) }}</strong></td>
                            <td class="num"><strong>{{ $n($t['disponivel']) }}</strong></td>
                            <td class="num"><strong>{{ $n($t['vendas']) }}</strong></td>
                            <td class="num {{ $sinal($t['posicao']) }}"><strong>{{ $n($t['posicao']) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div style="margin:0 0 22px; font-size:12.5px; color:var(--muted); line-height:1.6;">
        @if ($foto)
            <strong>Foto do estoque:</strong> {{ $foto->data_foto->format('d/m/Y') }}@if ($foto->origem) — {{ $foto->origem }}@endif.
            Entregas e baixas com data a partir dela entram sozinhas; uma foto nova só re-ancora a conta.
            @if ($p['semPreparo'])
                <br><strong>Café ainda sem preparo na foto</strong> (distribuído pelo rendimento):
                @foreach ($p['semPreparo'] as $balde => $sacas)
                    {{ $baldes[$balde] ?? $balde }} {{ $n($sacas) }} sc
                    ({{ collect(config("posicao.rendimento_estoque.{$balde}"))->filter()->map(fn ($pct, $destino) => $pct . '% ' . ($baldes[$destino] ?? $destino))->implode(', ') }}){{ $loop->last ? '.' : ';' }}
                @endforeach
            @endif
            <br>
        @endif
        <strong>Compras:</strong> viram padrão pelo % de 17/18 e de cata da compra — o resto é 14/16;
        da cata, {{ config('posicao.grinders_sobre_cata') }}% é grinders e o restante vai para o mercado interno.
        <br>
        <strong>Mercado interno</strong> (fora da posição de exportação): {{ $n($p['interno']['disponivel']) }} sc
        — {{ $n($p['interno']['estoque_foto']) }} em estoque, {{ $n($p['interno']['entradas']) }} em entregas e
        {{ $n($p['interno']['a_receber']) }} a receber. As vendas de mercado interno ainda não estão no sistema.
        <br>
        As vendas mês a mês estão detalhadas no <a href="{{ route('embarques.index') }}">Controle logístico</a>.
    </div>
@endsection
