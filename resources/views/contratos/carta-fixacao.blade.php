<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; }
    @page { margin: 28px 34px; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1a1a1a; line-height: 1.45; }

    .head { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    .head td { vertical-align: middle; }
    .head .logo { width: 70px; }
    .head .logo img { width: 58px; height: auto; }
    .company { font-size: 10.5px; color: #0C4028; }
    .company .nm { font-size: 13px; font-weight: bold; letter-spacing: .02em; }
    .rule { border: 0; border-top: 2px solid #0C4028; margin: 8px 0 12px; }

    h1 { font-size: 15px; letter-spacing: .06em; text-align: center; margin: 0 0 4px; color: #0C4028; }
    .date { text-align: right; font-size: 10.5px; margin: 0 0 10px; }
    .lead { margin: 0 0 12px; font-size: 11px; }

    table.body { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    table.body td { vertical-align: top; padding: 4px 0; border-bottom: 1px solid #e6e6e6; }
    table.body td.lbl { width: 150px; color: #0C4028; font-weight: bold; font-size: 10px;
        letter-spacing: .04em; text-transform: uppercase; }
    .pre { white-space: pre-line; }
    .b { font-weight: bold; }

    table.tranches { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.tranches th { background: #0C4028; color: #fff; font-size: 9px; letter-spacing: .05em;
        text-transform: uppercase; padding: 6px 8px; text-align: left; }
    table.tranches td { padding: 6px 8px; border-bottom: 1px solid #e6e6e6; }
    table.tranches tr:nth-child(even) td { background: #f7f6f2; }
    .num { text-align: right; }

    table.final { width: 58%; border-collapse: collapse; margin-left: auto; }
    table.final td { padding: 5px 8px; border-bottom: 1px solid #e6e6e6; font-size: 11px; }
    table.final td.lbl { color: #0C4028; font-weight: bold; text-transform: uppercase;
        font-size: 9.5px; letter-spacing: .04em; }
    table.final tr.destaque td { border-top: 2px solid #0C4028; border-bottom: 0; font-size: 12.5px; font-weight: bold; }

    .aberto { margin-top: 12px; padding: 8px 10px; background: #FCF3DC; color: #8A6116; font-size: 10.5px; }

    .sign { width: 100%; margin-top: 42px; border-collapse: collapse; }
    .sign td { width: 50%; text-align: center; font-size: 10.5px; color: #333; vertical-align: bottom; }
    .sign .role { padding-bottom: 80px; }
    .sign .line { border-top: 1px solid #333; padding-top: 6px; }
    .foot { margin-top: 18px; font-size: 8.5px; color: #999; text-align: center; }
</style>
</head>
<body>
@php
    $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('img/union-trading.png')));
    $utNum = preg_replace('/\D+/', '', $contrato->numero_ut) ?: $contrato->numero_ut;
    $n = fn ($v) => number_format((float) $v, 2, ',', '.');
    // Unidade por extenso, como a contraparte escreve: "Cents USD/Lb".
    $unidade = $contrato->porto === 'VITORIA' ? 'USD/MT' : 'Cents USD/Lb';
@endphp

<table class="head">
    <tr>
        <td class="logo"><img src="{{ $logo }}" alt="Union Trading"></td>
        <td class="company">
            <div class="nm">UNION TRADING COMÉRCIO IMPORTAÇÃO E EXPORTAÇÃO LTDA</div>
            Cep: 13.990-029 / Espírito Santo do Pinhal - SP, Brasil<br>
            CNPJ: 11.881.236/0001-09 &nbsp; IE: 530.055.829.118<br>
            Phone: +55 00 0000-0000 &nbsp;&nbsp; Email: usuario3@example.com
        </td>
    </tr>
</table>
<hr class="rule">

<h1>PRICE FIXING CONFIRMATION</h1>
<p class="date">Date: {{ now()->format('d/m/Y') }}</p>

<p class="lead">We confirm fixation as follows:</p>

<table class="body">
    <tr><td class="lbl">To</td><td class="pre"><span class="b">{{ $contrato->cliente_nome }}</span>
{{ $contrato->cliente_endereco }}</td></tr>
    <tr><td class="lbl">From</td><td>UNION TRADING COMÉRCIO IMP E EXPORTAÇÃO LTDA</td></tr>
    {{-- Somos o SELLER: a nossa referência é o UT e a do comprador é a
         buyer_ref. Na carta que o cliente manda os dois aparecem trocados de
         lado, porque lá quem escreve é o comprador. --}}
    <tr><td class="lbl">Seller's Ref.</td><td class="b">UT {{ $utNum }}</td></tr>
    <tr><td class="lbl">Buyer's Ref.</td><td>{{ $contrato->buyer_ref ?: '—' }}</td></tr>
    <tr><td class="lbl">Quantity</td><td>{{ $contrato->quantidadeLinha() }}</td></tr>
    <tr><td class="lbl">Description</td><td>{{ $contrato->qualidade_descricao }}</td></tr>
    @if ($contrato->embarqueLinha())
        <tr><td class="lbl">Shipment/Delivery</td><td>{{ $contrato->embarqueLinha() }}</td></tr>
    @endif
    <tr><td class="lbl">Position</td><td>{{ $contrato->incotermsLinha() }}</td></tr>
</table>

<table class="tranches">
    <thead>
        <tr>
            <th>Date</th>
            <th class="num">Lots</th>
            <th>Month</th>
            <th class="num">Dif.</th>
            <th class="num">Price</th>
            <th class="num">Balance</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($linhas as $linha)
            @php $f = $linha['fixacao']; @endphp
            <tr>
                <td>{{ $f->created_at?->format('d/m/Y') }}</td>
                <td class="num">{{ $f->lotes }}</td>
                <td>{{ \App\Models\Contrato::bolsaEMesPorExtenso($f->tela) ?? '—' }}</td>
                <td class="num">{{ $n($f->diferencial) }}</td>
                <td class="num">{{ $n($f->level) }}</td>
                <td class="num saldo">{{ $linha['saldo'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Os dois números não são a mesma coisa: a MÉDIA é dos levels (o preço da
     bolsa) e o PREÇO FINAL já traz o diferencial embutido. --}}
<table class="final">
    <tr>
        <td class="lbl">Average</td>
        <td class="num">{{ $n($resumo['media_level']) }} {{ $unidade }}</td>
    </tr>
    <tr>
        <td class="lbl">Differential</td>
        <td class="num">{{ $n($resumo['media_diferencial']) }} {{ $unidade }}</td>
    </tr>
    <tr class="destaque">
        <td class="lbl">Final Price</td>
        <td class="num">{{ $n($resumo['preco_final']) }} {{ $unidade }}</td>
    </tr>
</table>

@unless ($resumo['completo'])
    {{-- Carta parcial: dizer isso em voz alta, senão o "Final Price" passa
         por preço fechado quando ainda falta fixar lote. --}}
    <p class="aberto">
        <b>Partial fixation.</b> {{ $resumo['lotes_fixados'] }} of {{ $contrato->lotes }} lot(s) fixed;
        {{ $resumo['saldo'] }} lot(s) still open. The figures above cover the fixed lots only.
    </p>
@endunless

<table class="sign">
    <tr>
        <td class="role">UNION TRADING</td>
        <td class="role">{{ $contrato->cliente_nome }}</td>
    </tr>
    <tr>
        <td class="line">Seller</td>
        <td class="line">Buyer</td>
    </tr>
</table>

<div class="foot">Union Trading</div>
</body>
</html>
