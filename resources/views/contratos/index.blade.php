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
    {{-- Com a carga das vendas a lista passou de sete páginas: sem busca,
         achar um contrato era folhear. --}}
    <form method="GET" action="{{ route('contratos.index') }}" class="filter-bar">
        <div class="field" style="min-width:280px;">
            <label for="busca">Buscar (UT, comprador ou ref. do comprador)</label>
            <input type="search" id="busca" name="busca" value="{{ $busca }}" placeholder="Ex.: 5973, ALFA, ECT 3977">
        </div>
        <button type="submit" class="btn btn-primary">Buscar</button>
        @if ($busca !== '')
            <a href="{{ route('contratos.index') }}" class="btn btn-ghost">Limpar</a>
            <span style="align-self:center; font-size:13px; color:var(--muted);">
                {{ $contratos->total() === 1 ? '1 contrato encontrado' : $contratos->total() . ' contratos encontrados' }}
            </span>
        @endif
    </form>

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
                    <th>Preço</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contratos as $contrato)
                    <tr class="{{ $contrato->cancelado() ? 'linha-cancelada' : '' }}">
                        <td data-label="UT"><strong>UT {{ preg_replace('/\D+/', '', $contrato->numero_ut) ?: $contrato->numero_ut }}</strong></td>
                        <td data-label="Data">{{ $contrato->data_contrato->format('d/m/Y') }}</td>
                        <td data-label="Comprador">{{ $contrato->cliente_nome }}</td>
                        <td data-label="Qualidade">{{ \Illuminate\Support\Str::limit($contrato->qualidade_descricao, 40) }}</td>
                        <td class="num" data-label="Qtde (kg)">{{ number_format((float) $contrato->quantidade_kg, 0, ',', '.') }}</td>
                        <td class="num" data-label="Lotes">{{ $contrato->lotes }}</td>
                        <td data-label="Preço">
                            @if ($contrato->cancelado())
                                <span class="badge badge--red" title="{{ $contrato->motivo_cancelamento }}">CANCELADO</span>
                            @elseif ($contrato->fixado)
                                <span class="badge badge--green">FIXED</span>
                            @elseif ($contrato->parcialmenteFixado())
                                <span class="badge badge--amber">PARCIAL {{ $contrato->lotesFixados() }}/{{ $contrato->lotes }}</span>
                            @else
                                <span class="badge badge--muted">A FIXAR</span>
                            @endif
                        </td>
                        {{-- O flex fica num <div> interno: aplicado direto no <td>, a
                             célula deixava de ser célula de tabela e os três botões
                             empilhavam, triplicando a altura de cada linha. --}}
                        <td class="cell-action">
                            <div style="display:flex; gap:6px; justify-content:flex-end; flex-wrap:nowrap;">
                            <a href="{{ route('contratos.show', $contrato) }}" class="btn btn-ghost" style="padding:6px 12px; font-size:13px;">Ver</a>
                            @unless ($contrato->cancelado())
                                <a href="{{ route('contratos.edit', $contrato) }}" class="btn btn-ghost" style="padding:6px 12px; font-size:13px;">Editar</a>
                            @endunless
                            <a href="{{ route('contratos.pdf', $contrato) }}" class="btn btn-primary js-save-pdf" data-filename="{{ $contrato->nomeArquivoPdf() }}" style="padding:6px 12px; font-size:13px;">PDF</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="cell-empty" colspan="8" style="text-align:center; color:var(--muted); padding:24px;">
                        {{ $busca !== '' ? 'Nenhum contrato encontrado para "' . $busca . '".' : 'Nenhum contrato gerado ainda.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($contratos->hasPages())
        <div class="pagination">{{ $contratos->links() }}</div>
    @endif
@endsection
