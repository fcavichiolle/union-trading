@extends('layouts.app')

@section('title', 'Contratos gerados')
@section('subtitle', 'Contratos de exportação criados no sistema.')

@section('crumb')
    <span>Contratos</span><span class="sep">/</span><b>Gerados</b>
@endsection

@section('page_actions')
    <a href="{{ route('contratos.create') }}" class="btn-coffee">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5.5v13M5.5 12h13"></path></svg>
        <span>Novo contrato</span>
    </a>
@endsection

@section('content')
    <div class="table-wrap">
        <table class="data data--cards">
            <thead>
                <tr>
                    <th>UT</th>
                    <th>Data</th>
                    <th>Comprador</th>
                    <th>Qualidade</th>
                    <th class="num">Qtde (kg)</th>
                    <th class="num">Lotes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contratos as $contrato)
                    <tr>
                        <td data-label="UT"><strong>UT {{ preg_replace('/\D+/', '', $contrato->numero_ut) ?: $contrato->numero_ut }}</strong></td>
                        <td data-label="Data">{{ $contrato->data_contrato->format('d/m/Y') }}</td>
                        <td data-label="Comprador">{{ $contrato->cliente_nome }}</td>
                        <td data-label="Qualidade">{{ \Illuminate\Support\Str::limit($contrato->qualidade_descricao, 40) }}</td>
                        <td class="num" data-label="Qtde (kg)">{{ number_format((float) $contrato->quantidade_kg, 0, ',', '.') }}</td>
                        <td class="num" data-label="Lotes">{{ $contrato->lotes }}</td>
                        <td class="cell-action" style="display:flex; gap:6px; justify-content:flex-end;">
                            <a href="{{ route('contratos.show', $contrato) }}" class="btn btn-ghost" style="padding:6px 12px; font-size:13px;">Ver</a>
                            <a href="{{ route('contratos.pdf', $contrato) }}" class="btn btn-primary js-save-pdf" data-filename="{{ $contrato->nomeArquivoPdf() }}" style="padding:6px 12px; font-size:13px;">PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr><td class="cell-empty" colspan="7" style="text-align:center; color:var(--muted); padding:24px;">Nenhum contrato gerado ainda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($contratos->hasPages())
        <div class="pagination">{{ $contratos->links() }}</div>
    @endif
@endsection
