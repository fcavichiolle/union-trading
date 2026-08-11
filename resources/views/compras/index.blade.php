@extends('layouts.app')

@section('title', 'Compras lançadas')
@section('subtitle', 'Negócios fechados e o quanto já entrou no armazém.')

@section('crumb')
    <span>Compras &amp; Classificação</span><span class="sep">/</span><b>Compras lançadas</b>
@endsection

@section('page_actions')
    <a href="{{ route('compras.create') }}" class="btn-coffee">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5.5v13M5.5 12h13"></path></svg>
        <span>Nova compra</span>
    </a>
@endsection

@section('content')
    <form method="GET" action="{{ route('compras.index') }}" class="filter-bar">
        <div class="field">
            <label for="mes_de">Compra de</label>
            <input type="month" id="mes_de" name="mes_de" value="{{ request('mes_de') }}">
        </div>
        <div class="field">
            <label for="mes_ate">Compra até</label>
            <input type="month" id="mes_ate" name="mes_ate" value="{{ request('mes_ate') }}">
        </div>
        <div class="field">
            <label for="armazem">Armazém</label>
            <select id="armazem" name="armazem">
                <option value="">Todos</option>
                @foreach (\App\Models\Compra::armazens() as $cod => $rotulo)
                    <option value="{{ $cod }}" @selected(request('armazem') === $cod)>{{ $rotulo }}</option>
                @endforeach
            </select>
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
            <label for="pendencia">Pendência</label>
            <select id="pendencia" name="pendencia">
                <option value="">Todas</option>
                <option value="qualquer" @selected(request('pendencia') === 'qualquer')>Qualquer pendência</option>
                <option value="sem_lote" @selected(request('pendencia') === 'sem_lote')>Entrega sem nº do lote</option>
                <option value="saldo_a_entregar" @selected(request('pendencia') === 'saldo_a_entregar')>Com saldo a entregar</option>
                <option value="divergente" @selected(request('pendencia') === 'divergente')>Divergência a liquidar</option>
                <option value="sem_classificacao" @selected(request('pendencia') === 'sem_classificacao')>Sem classificação</option>
                <option value="sem_preco" @selected(request('pendencia') === 'sem_preco')>Sem preço</option>
                <option value="sem_documento" @selected(request('pendencia') === 'sem_documento')>Vendedor a confirmar</option>
            </select>
        </div>
        <div class="field">
            <label for="busca">Buscar (UTS ou fornecedor)</label>
            <input type="search" id="busca" name="busca" value="{{ request('busca') }}" placeholder="Ex: UTS 7312">
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        @if (request()->hasAny(['mes_de', 'mes_ate', 'armazem', 'padrao', 'pendencia', 'busca']))
            <a href="{{ route('compras.index') }}" class="btn btn-ghost">Limpar</a>
        @endif
    </form>

    <div class="table-wrap">
        <table class="data data--cards">
            <thead>
                <tr>
                    <th>UTS</th>
                    <th>Data</th>
                    <th>Vendedor</th>
                    <th>Certificação</th>
                    <th class="num">Contratado (sc)</th>
                    <th class="num">Entregue (sc)</th>
                    <th>Entregas</th>
                    <th>Padrão</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($compras as $compra)
                    @php
                        $entregues = $compra->sacasEntregues();
                        $saldo = $compra->saldoAEntregar();
                        $semLote = $compra->entregas->filter(fn ($e) => $e->precisaDeNumeroLote())->count();
                    @endphp
                    <tr>
                        <td data-label="UTS"><strong>{{ $compra->uts }}</strong></td>
                        <td data-label="Data">{{ $compra->data_compra?->format('d/m/Y') ?? '—' }}</td>
                        <td data-label="Vendedor">
                            {{ \Illuminate\Support\Str::limit($compra->fornecedor?->nome, 24) }}
                            @unless ($compra->fornecedor?->documento)
                                <br><span class="badge badge--amber">CNPJ/CPF a confirmar</span>
                            @endunless
                        </td>
                        <td data-label="Certificação">{{ \App\Models\Compra::certificacoes()[$compra->certificacao] ?? $compra->certificacao }}</td>
                        <td class="num" data-label="Contratado (sc)">{{ number_format((float) $compra->volume_contratado, 2, ',', '.') }}</td>
                        <td class="num" data-label="Entregue (sc)">
                            {{ number_format($entregues, 2, ',', '.') }}
                            {{-- Liquidada = o entregue é o final: nada de aviso. --}}
                            @if ($compra->liquidada())
                                <br><span class="badge badge--green" title="Liquidada em {{ $compra->liquidada_em->format('d/m/Y') }} — este volume é o final.">liquidada</span>
                            @elseif ($saldo > 0.01)
                                <br><span class="badge badge--amber">faltam {{ number_format($saldo, 0, ',', '.') }}</span>
                            @elseif ($saldo < -0.01)
                                <br><span class="badge badge--amber">+{{ number_format(abs($saldo), 0, ',', '.') }} a mais</span>
                            @endif
                        </td>
                        <td data-label="Entregas">
                            @if ($compra->entregas->isEmpty())
                                <span class="badge badge--muted">nenhuma</span>
                            @else
                                {{ $compra->entregas->count() }}
                                <span style="color:var(--muted); font-size:11.5px;">
                                    ({{ $compra->entregas->pluck('armazem')->unique()->map(fn ($a) => \App\Models\Compra::armazens()[$a] ?? $a)->implode(', ') }})
                                </span>
                                @if ($semLote > 0)
                                    <br><span class="badge badge--red" title="Sem o nº do lote, não entra no estoque definitivo.">⚠ {{ $semLote }} sem lote</span>
                                @endif
                            @endif
                        </td>
                        <td data-label="Padrão">
                            @if ($compra->classificacao)
                                <span class="badge badge--green">{{ \App\Models\Classificacao::padroes()[$compra->classificacao->padrao_final] ?? $compra->classificacao->padrao_final }}</span>
                                @if ($compra->classificacao->tipoBebidaLabel())
                                    <br><span style="color:var(--muted); font-size:11.5px;">{{ $compra->classificacao->tipoBebidaLabel() }}</span>
                                @endif
                            @else
                                <span class="badge badge--muted">Não classificada</span>
                            @endif
                        </td>
                        <td class="cell-action"><a href="{{ route('compras.show', $compra) }}" class="btn btn-ghost" style="padding:6px 12px; font-size:13px;">Abrir</a></td>
                    </tr>
                @empty
                    <tr><td class="cell-empty" colspan="9" style="text-align:center; color:var(--muted); padding:24px;">Nenhuma compra encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">{{ $compras->links() }}</div>
@endsection
