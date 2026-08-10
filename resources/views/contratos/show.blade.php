@extends('layouts.app')

@section('title', 'UT ' . (preg_replace('/\D+/', '', $contrato->numero_ut) ?: $contrato->numero_ut))
@section('subtitle', $contrato->cliente_nome . ' · ' . $contrato->data_contrato->format('d/m/Y'))

@section('crumb')
    <span>Contratos</span><span class="sep">/</span>
    <a href="{{ route('contratos.index') }}" style="color:inherit;">Gerados</a><span class="sep">/</span><b>UT {{ preg_replace('/\D+/', '', $contrato->numero_ut) }}</b>
@endsection

@section('page_actions')
    <a href="{{ route('contratos.pdf', $contrato) }}" class="btn-coffee js-save-pdf" data-filename="{{ $contrato->nomeArquivoPdf() }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"></path></svg>
        <span>Baixar PDF</span>
    </a>
@endsection

@section('content')
    <div class="calc-grid" style="margin-bottom:20px;">
        <div class="calc-item"><span class="calc-lbl">Sacas</span><span class="calc-val">{{ number_format((float) $contrato->sacas, 2, ',', '.') }}</span></div>
        <div class="calc-item"><span class="calc-lbl">Lotes</span><span class="calc-val">{{ $contrato->lotes }}</span></div>
        <div class="calc-item"><span class="calc-lbl">Containers</span><span class="calc-val">{{ $contrato->containers }}</span></div>
        <div class="calc-item"><span class="calc-lbl">Peso / container</span><span class="calc-val">{{ number_format((float) $contrato->kg_por_container / 1000, 2, ',', '.') }} t</span></div>
    </div>

    <div class="card">
        <div class="card__header"><h2>Dados do contrato</h2></div>
        <div class="card__body">
            <table class="data" style="border:0;">
                <tbody>
                    <tr><td style="width:220px; color:var(--muted);">BUYER</td><td style="white-space:pre-line;"><strong>{{ $contrato->cliente_nome }}</strong>{{ "\n" . $contrato->cliente_endereco }}</td></tr>
                    <tr><td style="color:var(--muted);">BUYER REF NR</td><td>{{ $contrato->buyer_ref ?: '—' }}</td></tr>
                    <tr><td style="color:var(--muted);">QUALITY</td><td>{{ $contrato->qualidade_descricao }} <span class="badge badge--muted">{{ $contrato->tipo_cafe === 'CONILON' ? 'Conilon' : 'Arábica' }}</span></td></tr>
                    <tr><td style="color:var(--muted);">CERTIFIED</td><td>{{ $contrato->certificadoLabel() }}</td></tr>
                    <tr><td style="color:var(--muted);">QUANTITY</td><td>{{ $contrato->quantidadeLinha() }}</td></tr>
                    <tr><td style="color:var(--muted);">PACKAGING</td><td>{{ $contrato->embalagem }}</td></tr>
                    <tr><td style="color:var(--muted);">STATUS</td><td>
                        @if ($contrato->fixado)
                            <span class="badge badge--green">FIXED</span>
                        @else
                            <span class="badge badge--muted">A FIXAR</span>
                        @endif
                    </td></tr>
                    <tr><td style="color:var(--muted);">PRICE</td><td>{{ $contrato->precoLinha() }}</td></tr>
                    <tr><td style="color:var(--muted);">SHIPMENT</td><td>{{ $contrato->embarqueLinha() ?: '—' }}</td></tr>
                    <tr><td style="color:var(--muted);">INCOTERMS</td><td>{{ $contrato->incotermsLinha() }}</td></tr>
                    <tr><td style="color:var(--muted);">REMARKS</td><td style="white-space:pre-line;">{{ $contrato->remarks ?: '—' }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
