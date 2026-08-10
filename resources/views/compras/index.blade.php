@extends('layouts.app')

@section('title', 'Compras lançadas')

@section('content')
    <form method="GET" action="{{ route('compras.index') }}" class="filter-bar">
        <div class="field">
            <label for="mes_de">Mês inicial</label>
            <input type="month" id="mes_de" name="mes_de" value="{{ request('mes_de') }}">
        </div>
        <div class="field">
            <label for="mes_ate">Mês final</label>
            <input type="month" id="mes_ate" name="mes_ate" value="{{ request('mes_ate') }}">
        </div>
        <div class="field">
            <label for="padrao">Padrão</label>
            <select id="padrao" name="padrao">
                <option value="">Todos</option>
                @foreach (\App\Models\Classificacao::padroes() as $cod => $rotulo)
                    <option value="{{ $cod }}" @selected(request('padrao') === $cod)>{{ $rotulo }}</option>
                @endforeach
                <option value="SEM_CLASSIFICACAO" @selected(request('padrao') === 'SEM_CLASSIFICACAO')>Não classificada</option>
            </select>
        </div>
        <div class="field">
            <label for="busca">Buscar (UTS ou fornecedor)</label>
            <input type="search" id="busca" name="busca" value="{{ request('busca') }}" placeholder="Ex: UTS-2026-001">
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        @if (request()->hasAny(['mes_de', 'mes_ate', 'padrao', 'busca']))
            <a href="{{ route('compras.index') }}" class="btn btn-ghost">Limpar</a>
        @endif
    </form>

    <div class="table-wrap">
        <table class="data data--cards">
            <thead>
                <tr>
                    <th>UTS</th>
                    <th>Mês/Ano</th>
                    <th>Fornecedor</th>
                    <th>Armazém</th>
                    <th>Certificação</th>
                    <th class="num">Volume (sc)</th>
                    <th class="num">Merc. interno (sc)</th>
                    <th class="num">Grinders (sc)</th>
                    <th>Padrão</th>
                    <th>Lote</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($compras as $compra)
                    <tr>
                        <td data-label="UTS">{{ $compra->uts }}</td>
                        <td data-label="Mês/Ano">{{ $compra->mes_ano->format('m/Y') }}</td>
                        <td data-label="Fornecedor">{{ $compra->fornecedor->nome }}</td>
                        <td data-label="Armazém">{{ \App\Models\Compra::armazens()[$compra->armazem] }}</td>
                        <td data-label="Certificação">{{ \App\Models\Compra::certificacoes()[$compra->certificacao] ?? $compra->certificacao }}</td>
                        <td class="num" data-label="Volume (sc)">{{ number_format($compra->volume_sacas, 2, ',', '.') }}</td>
                        <td class="num" data-label="Merc. interno (sc)">{{ $compra->classificacao ? number_format($compra->classificacao->mercado_interno_sacas, 2, ',', '.') : '—' }}</td>
                        <td class="num" data-label="Grinders (sc)">{{ $compra->classificacao ? number_format($compra->classificacao->grinders_sacas, 2, ',', '.') : '—' }}</td>
                        <td data-label="Padrão">
                            @if ($compra->classificacao)
                                <span class="badge badge--green">{{ \App\Models\Classificacao::padroes()[$compra->classificacao->padrao_final] ?? $compra->classificacao->padrao_final }}</span>
                            @else
                                <span class="badge badge--muted">Não classificada</span>
                            @endif
                        </td>
                        <td data-label="Lote">
                            @if ($compra->precisaDeNumeroLote())
                                <span class="badge badge--red" title="Esta compra ainda não pode ser considerada definitivamente em estoque.">⚠ Falta nº do lote</span>
                            @else
                                <span class="badge badge--green">{{ $compra->numero_lote }}</span>
                            @endif
                        </td>
                        <td class="cell-action"><a href="{{ route('compras.show', $compra) }}" class="btn btn-ghost" style="padding:6px 12px; font-size:13px;">Abrir</a></td>
                    </tr>
                @empty
                    <tr><td class="cell-empty" colspan="11" style="text-align:center; color:var(--muted); padding:24px;">Nenhuma compra encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">{{ $compras->links() }}</div>
@endsection