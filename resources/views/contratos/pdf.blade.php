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
    .rule { border: 0; border-top: 2px solid #0C4028; margin: 8px 0 10px; }
    .confirm { font-size: 11px; margin: 0 0 10px; }
    .confirm .date { float: right; font-weight: bold; }
    table.body { width: 100%; border-collapse: collapse; }
    table.body td { vertical-align: top; padding: 4px 0; border-bottom: 1px solid #e6e6e6; }
    table.body td.lbl { width: 165px; color: #0C4028; font-weight: bold; font-size: 10px; letter-spacing: .04em; text-transform: uppercase; }
    table.body td.val { color: #1a1a1a; }
    .val .b { font-weight: bold; }
    .pre { white-space: pre-line; }
    .sign { width: 100%; margin-top: 40px; border-collapse: collapse; }
    .sign td { width: 50%; text-align: center; font-size: 10.5px; color: #333; vertical-align: bottom; }
    /* espaço amplo entre o rótulo e a linha para caber assinatura + carimbo */
    .sign .role { padding-bottom: 90px; }
    .sign .line { border-top: 1px solid #333; padding-top: 6px; }
    .foot { margin-top: 18px; font-size: 8.5px; color: #999; text-align: center; }
</style>
</head>
<body>
@php
    $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('img/union-trading.png')));
    $utNum = preg_replace('/\D+/', '', $contrato->numero_ut) ?: $contrato->numero_ut;
@endphp

<table class="head">
    <tr>
        <td class="logo"><img src="{{ $logo }}" alt="Union Trading"></td>
        <td class="company">
            {{-- Cabeçalho conforme o texto passado pela mesa (12/ago/2026):
                 saíram os underlines e o "nr."/":-", e o CEP passou a
                 13.990-029. --}}
            <div class="nm">UNION TRADING COMÉRCIO IMPORTAÇÃO E EXPORTAÇÃO LTDA</div>
            Cep: 13.990-029 / Espírito Santo do Pinhal - SP, Brasil<br>
            CNPJ: 11.881.236/0001-09 &nbsp; IE: 530.055.829.118<br>
            Phone: +55 19 3651-8442 &nbsp;&nbsp; Email: lhenrique@utrading.com.br
        </td>
    </tr>
</table>
<hr class="rule">

<p class="confirm"><span class="date">Date: {{ $contrato->data_contrato->format('d/m/Y') }}</span>We are pleased to confirm the following business:</p>

<table class="body">
    <tr><td class="lbl">Seller Ref Nr</td><td class="val"><span class="b">UT {{ $utNum }}</span></td></tr>
    {{-- Endereço do Seller conforme o texto passado pela mesa (12/ago/2026).
         A classe "pre" preserva as quebras de linha, então o recuo aqui é
         proposital: mexer nele muda o PDF. --}}
    <tr><td class="lbl">Seller</td><td class="val pre">UNION TRADING COMÉRCIO IMP E EXPORTAÇÃO LTDA
Rua: Duque de Caxias, 238 - Centro.
CEP: 13.990-029 / Espírito Santo do Pinhal - SP, Brasil</td></tr>
    <tr><td class="lbl">Shipper</td><td class="val">UNION TRADING COMÉRCIO IMP E EXPORTAÇÃO LTDA</td></tr>
    <tr><td class="lbl">Buyer</td><td class="val pre"><span class="b">{{ $contrato->cliente_nome }}</span>
{{ $contrato->cliente_endereco }}</td></tr>
    <tr><td class="lbl">Buyer Ref Nr</td><td class="val">{{ $contrato->buyer_ref }}</td></tr>
    <tr><td class="lbl">Quality</td><td class="val">{{ $contrato->qualidade_descricao }}</td></tr>
    <tr><td class="lbl">Certified</td><td class="val">{{ $contrato->certificadoLabel() }}</td></tr>
    <tr><td class="lbl">Quantity</td><td class="val">{{ $contrato->quantidadeLinha() }}</td></tr>
    <tr><td class="lbl">Packaging</td><td class="val">{{ $contrato->embalagem }}</td></tr>
    <tr><td class="lbl">Price</td><td class="val">{{ $contrato->precoLinha() }}</td></tr>
    <tr><td class="lbl">Shipment</td><td class="val">{{ $contrato->embarqueLinha() }}</td></tr>
    <tr><td class="lbl">Incoterms</td><td class="val">{{ $contrato->incotermsLinha() }}</td></tr>
    <tr><td class="lbl">Destination</td><td class="val">T.B.I</td></tr>
    {{-- Redação da cláusula palavra por palavra; só o separador mudou de
         " _ " para " - " (pedido da mesa, 12/ago/2026). --}}
    <tr><td class="lbl">Payment</td><td class="val">CAD - Cash Against documents on first presentation.</td></tr>
    <tr><td class="lbl">Arbitration</td><td class="val">In London, at the British Coffee Association Arbitration Service</td></tr>
    <tr><td class="lbl">Other Conditions</td><td class="val">European Coffee Contract, latest edition</td></tr>
    <tr><td class="lbl">Applicable Law</td><td class="val">The uniform law on the international sale of goods shall not apply to this contract.</td></tr>
    <tr><td class="lbl">Remarks</td><td class="val pre">{{ $contrato->remarks }}</td></tr>
</table>

<table class="sign">
    <tr><td class="role">The Buyer:</td><td class="role">The Seller:</td></tr>
    <tr>
        <td><div class="line">{{ $contrato->cliente_nome }}</div></td>
        <td><div class="line">UNION TRADING COM IMP E EXP LTDA</div></td>
    </tr>
</table>

<div class="foot">Union Trading · contrato gerado eletronicamente</div>
</body>
</html>
