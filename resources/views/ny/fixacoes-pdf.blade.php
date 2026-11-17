<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; }
    @page { margin: 26px 30px; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1a1a1a; line-height: 1.4; }

    .head { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    .head td { vertical-align: middle; }
    .head .logo { width: 62px; }
    .head .logo img { width: 52px; height: auto; }
    .company { font-size: 9.5px; color: #0C4028; }
    .company .nm { font-size: 12px; font-weight: bold; letter-spacing: .02em; }
    .head .meta { text-align: right; font-size: 9px; color: #555; }
    .rule { border: 0; border-top: 2px solid #0C4028; margin: 8px 0 10px; }

    h1 { font-size: 14px; margin: 0 0 3px; color: #0C4028; }
    .recorte { margin: 0 0 10px; font-size: 9.5px; color: #555; }
    .recorte b { color: #1a1a1a; }

    table.data { width: 100%; border-collapse: collapse; }
    table.data th { background: #0C4028; color: #fff; font-size: 8.5px; letter-spacing: .04em;
        text-transform: uppercase; padding: 5px 6px; text-align: left; }
    table.data td { padding: 5px 6px; border-bottom: 1px solid #e6e6e6; vertical-align: top; }
    table.data tr:nth-child(even) td { background: #f7f6f2; }
    .num { text-align: right; }
    .sub { color: #666; font-size: 8.5px; }

    table.totais { width: auto; border-collapse: collapse; margin-top: 14px; }
    table.totais th, table.totais td { padding: 4px 10px; border-bottom: 1px solid #e6e6e6; font-size: 9.5px; }
    table.totais th { text-align: left; color: #0C4028; text-transform: uppercase; font-size: 8.5px; letter-spacing: .04em; }
    .nota { margin-top: 10px; font-size: 8.5px; color: #777; }
    .foot { margin-top: 16px; font-size: 8px; color: #999; text-align: center; }
    .vazio { padding: 24px; text-align: center; color: #777; }
</style>
</head>
<body>
@php
    $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('img/union-trading.png')));
    $n = fn ($v, $casas = 2) => number_format((float) $v, $casas, ',', '.');
@endphp

<table class="head">
    <tr>
        <td class="logo"><img src="{{ $logo }}" alt="Union Trading"></td>
        <td class="company">
            <div class="nm">UNION TRADING COMÉRCIO IMPORTAÇÃO E EXPORTAÇÃO LTDA</div>
            Cep: 13.990-029 / Espírito Santo do Pinhal - SP, Brasil<br>
            CNPJ: 11.881.236/0001-09 &nbsp; IE: 530.055.829.118
        </td>
        <td class="meta">
            Gerado em {{ now()->format('d/m/Y H:i') }}<br>
            @if ($geradoPor) por {{ $geradoPor }} @endif
        </td>
    </tr>
</table>
<hr class="rule">

<h1>Fixações registradas</h1>

<p class="recorte">
    @if ($filtros === [])
        Todas as fixações registradas.
    @else
        Recorte — <b>{{ implode(' · ', $filtros) }}</b>
    @endif
    &nbsp;|&nbsp; {{ $fixacoes->count() === 1 ? '1 registro' : $fixacoes->count() . ' registros' }}
</p>

@if ($fixacoes->isEmpty())
    <p class="vazio">Nenhuma fixação neste recorte.</p>
@else
<table class="data">
    <thead>
        <tr>
            <th>Data</th>
            <th>Contrato</th>
            <th>Cliente</th>
            <th>Corretora</th>
            <th>Tela</th>
            <th class="num">Lotes</th>
            <th class="num">Level</th>
            <th class="num">Dif.</th>
            <th class="num">Preço</th>
            <th>Por</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($fixacoes as $f)
            <tr>
                <td>{{ $f->created_at?->format('d/m/Y H:i') }}</td>
                <td><b>UT {{ $f->contrato?->numero_ut }}</b></td>
                <td>{{ $f->contrato?->cliente_nome }}</td>
                <td>
                    {{ $f->corretora }}
                    {{-- No PDF o comprador já tem coluna própria, então aqui
                         embaixo da corretora vai o BROKER do cliente — e com
                         esse rótulo, que era o que faltava. --}}
                    @if ($f->broker_cliente)
                        <div class="sub">broker: {{ $f->broker_cliente }}</div>
                    @endif
                </td>
                <td>{{ $f->tela ?: '—' }}</td>
                <td class="num">{{ $f->lotes }}</td>
                <td class="num">{{ $n($f->level) }}</td>
                <td class="num">{{ $n($f->diferencial) }}</td>
                <td class="num"><b>{{ $n($f->preco) }}</b> {{ $f->contrato?->unidadePreco() }}</td>
                <td>{{ $f->criadoPor?->name ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Uma linha por unidade: Santos é cts/lb e Vitória é USD/MT, e um total
     único misturando as duas seria um número sem significado. --}}
<table class="totais">
    <tr>
        <th>Unidade</th>
        <th class="num">Tranches</th>
        <th class="num">Lotes</th>
        <th class="num">Level médio (ponderado)</th>
    </tr>
    @foreach ($totais as $t)
        <tr>
            <td>{{ $t['unidade'] }}</td>
            <td class="num">{{ $t['tranches'] }}</td>
            <td class="num"><b>{{ $t['lotes'] }}</b></td>
            <td class="num">{{ $t['level_medio'] !== null ? $n($t['level_medio']) : '—' }}</td>
        </tr>
    @endforeach
</table>

<p class="nota">
    Level médio ponderado pelos lotes de cada tranche. O preço da tranche é
    level + diferencial; o AA se faz contra a bolsa, pelo level.
</p>
@endif

<div class="foot">Union Trading — documento interno</div>
</body>
</html>
