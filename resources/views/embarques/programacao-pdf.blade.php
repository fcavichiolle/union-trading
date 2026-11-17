<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; }
    @page { margin: 24px 28px; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5px; color: #1a1a1a; line-height: 1.4; }

    .head { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    .head td { vertical-align: middle; }
    .head .logo { width: 62px; }
    .head .logo img { width: 52px; height: auto; }
    .company { font-size: 9.5px; color: #0C4028; }
    .company .nm { font-size: 12px; font-weight: bold; letter-spacing: .02em; }
    .head .meta { text-align: right; font-size: 9px; color: #555; }
    .rule { border: 0; border-top: 2px solid #0C4028; margin: 6px 0 10px; }

    h1 { font-size: 15px; margin: 0 0 2px; color: #0C4028; letter-spacing: .04em; }
    .ref { font-size: 13px; font-weight: bold; margin: 0 0 10px; }
    .ref span { background: #FFF3A6; padding: 1px 5px; }

    .atencao { border: 1px solid #E0C766; background: #FFFBE0; padding: 8px 10px; margin-bottom: 12px; font-size: 10px; }
    .atencao b { display: block; margin-bottom: 3px; }
    .atencao p { margin: 0 0 4px; }

    table.grade { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    table.grade th { background: #0C4028; color: #fff; font-size: 8.5px; letter-spacing: .03em;
        text-transform: uppercase; padding: 5px 5px; text-align: left; border: 1px solid #0C4028; }
    table.grade td { padding: 6px 5px; border: 1px solid #b9c4bd; vertical-align: top; font-size: 10px; }
    table.grade td.destaque { background: #C9F2C9; font-weight: bold; }

    table.fumig th { background: #FFE600; color: #1a1a1a; border-color: #b8a600; }
    .fumig-empresa { display: inline-block; background: #C9F2C9; font-weight: bold; padding: 2px 8px; margin-bottom: 4px; }

    table.info { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    table.info td { padding: 3px 0; border-bottom: 1px solid #e6e6e6; vertical-align: top; }
    table.info td.lbl { width: 130px; color: #0C4028; font-weight: bold; font-size: 9px;
        letter-spacing: .04em; text-transform: uppercase; }

    .instrucoes { border-left: 3px solid #0C4028; padding: 4px 10px; margin-bottom: 12px; font-weight: bold;
        white-space: pre-line; }
    .foot { margin-top: 14px; font-size: 8px; color: #999; text-align: center; }
</style>
</head>
<body>
@php
    $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('img/union-trading.png')));
    $e = $programacao->embarque;
    $c = $e->contrato;
    $d = fn ($data) => $data ? $data->format('d/m/Y') : '-';
    $n = fn ($v) => number_format((float) $v, 0, ',', '.');
@endphp

<table class="head">
    <tr>
        <td class="logo"><img src="{{ $logo }}" alt="Union Trading"></td>
        <td class="company">
            <div class="nm">UNION TRADING COMÉRCIO IMPORTAÇÃO E EXPORTAÇÃO LTDA</div>
            Cep: 13.990-029 / Espírito Santo do Pinhal - SP, Brasil<br>
            Phone: +55 00 0000-0000
        </td>
        <td class="meta">
            Gerado em {{ now()->format('d/m/Y H:i') }}<br>
            @if ($geradoPor) por {{ $geradoPor }} @endif
        </td>
    </tr>
</table>
<hr class="rule">

<h1>PROGRAMAÇÃO DE ESTUFAGEM</h1>
<p class="ref"><span>{{ $programacao->referencia() }}</span></p>

{{-- Avisos fixos da logística: valem para toda programação. --}}
<div class="atencao">
    <b>TRANSPORTADORA / ARMAZÉM — ATENÇÃO</b>
    <p>Gentileza revisar toda a documentação por parte da transportadora e do armazém, para que, se necessária
        qualquer alteração em quaisquer documentos, seja feita antes das 17h00.</p>
    <p>Nosso horário de expediente finaliza às 17h30, dificultando o acesso a informações pertinentes aos
        embarques após este horário.</p>
    <p><b style="display:inline;">Notar que, se o caminhão precisar pernoitar em algum local, precisa ser em local
        monitorado.</b> Não podemos correr risco com caminhão em locais não monitorados.</p>
</div>

<table class="grade">
    <thead>
        <tr>
            <th>Fumig.</th>
            <th>Stuff</th>
            <th>DL doc</th>
            <th>DL carga</th>
            <th>Phyto</th>
            <th>Armazém</th>
            <th>Transportadora</th>
            <th>Destination</th>
            <th>ETD</th>
            <th>Voyage</th>
            <th>Booking</th>
            <th>ICO</th>
            <th>Qtd</th>
            <th>Packing</th>
            <th>Pilha</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $d($e->data_fumigacao) }}</td>
            <td class="destaque">{{ $d($e->data_estufagem) }}</td>
            <td>{{ $d($e->deadline_documentos) }}</td>
            <td>{{ $d($e->deadline_carga) }}</td>
            <td>{{ $d($e->data_fitossanitario) }}</td>
            <td>{{ $e->armazem?->nome ?? '-' }}</td>
            <td>{{ $e->transportadora ?: '-' }}</td>
            <td>{{ $e->destino ?: '-' }}</td>
            <td>{{ $d($e->etd) }}</td>
            <td>{{ $e->navio ?: '-' }}</td>
            <td>{{ $e->booking ?: '-' }}</td>
            <td>{{ $e->ico ?: '-' }}</td>
            <td>{{ $n($e->sacas) }}</td>
            <td>{{ $c->embalagem }}</td>
            <td>{{ $e->pilha ?: '-' }}</td>
        </tr>
    </tbody>
</table>

@if (filled($e->instrucoes))
    <div class="instrucoes">{{ $e->instrucoes }}</div>
@endif

<table class="info">
    <tr><td class="lbl">Qualidade</td><td>{{ $c->qualidade_descricao }}</td></tr>
    <tr><td class="lbl">Standard</td><td>{{ $c->standardLabel() ?? '-' }}</td></tr>
    <tr><td class="lbl">Certificação</td><td>{{ $c->certificadoLabel() }}</td></tr>
    <tr><td class="lbl">Peso</td><td>{{ $n($e->quantidade_kg) }} kg ({{ $n($e->sacas) }} sacas)</td></tr>
</table>

{{-- Bloco da fumigação: só quando há fumigação agendada. É a segunda
     tabela do e-mail, a que vai para a empresa de fumigação. --}}
@if ($e->data_fumigacao)
    @if (filled($e->fumigacao_empresa))
        <div class="fumig-empresa">{{ $e->fumigacao_empresa }}</div>
    @endif
    <table class="grade fumig">
        <thead>
            <tr>
                <th>Data da fumigação</th>
                <th>Nº contrato</th>
                <th>Armazém</th>
                <th>Sacas</th>
                <th>Peso</th>
                <th>Tipo de fumigação</th>
                <th>Tempo</th>
                <th>Destino</th>
                <th>Permit import</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $d($e->data_fumigacao) }}</td>
                <td>{{ $programacao->referencia() }}</td>
                <td>{{ $e->armazem?->nome ?? '-' }}</td>
                <td>Total de {{ $n($e->sacas) }} sacas</td>
                <td>{{ $n($e->quantidade_kg) }}</td>
                <td>{{ $e->fumigacao_tipo ?: '-' }}</td>
                <td>{{ $e->fumigacao_tempo ?: '-' }}</td>
                <td>{{ $e->destino ?: '-' }}</td>
                <td>{{ $e->permit_import ?: 'N/A' }}</td>
            </tr>
        </tbody>
    </table>
@endif

<div class="foot">Union Trading — programação de estufagem</div>
</body>
</html>
