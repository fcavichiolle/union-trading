@extends('layouts.app')

@section('title', 'Estoque')
@section('subtitle', 'Distribuição das sacas em estoque por armazém, padrão e peneira.')

@section('crumb')
    <span>Compras &amp; Classificação</span><span class="sep">/</span><b>Estoque</b>
@endsection

@section('content')
    <form method="GET" action="{{ route('relatorio.index') }}" class="filter-bar">
        <div class="field">
            <label for="mes_de">Mês inicial</label>
            <input type="month" id="mes_de" name="mes_de" value="{{ $filtros['mes_de'] }}">
        </div>
        <div class="field">
            <label for="mes_ate">Mês final</label>
            <input type="month" id="mes_ate" name="mes_ate" value="{{ $filtros['mes_ate'] }}">
        </div>
        <div class="field">
            <label for="armazem">Armazém</label>
            <select id="armazem" name="armazem">
                <option value="">Todos</option>
                @foreach (\App\Models\Armazem::lista() as $id => $nome)
                    <option value="{{ $id }}" @selected((int) $filtros['armazem'] === $id)>{{ $nome }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="situacao">Situação</label>
            <select id="situacao" name="situacao">
                @foreach (\App\Http\Controllers\Compras\DashboardController::SITUACOES as $cod => $rotulo)
                    <option value="{{ $cod }}" @selected($filtros['situacao'] === $cod)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="padrao">Padrão</label>
            <select id="padrao" name="padrao">
                <option value="">Todos</option>
                @foreach (\App\Models\Classificacao::padroes() as $cod => $rotulo)
                    <option value="{{ $cod }}" @selected($filtros['padrao'] === $cod)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="certificado">Certificado</label>
            <select id="certificado" name="certificado">
                <option value="">Todos</option>
                @foreach (\App\Models\Compra::certificacoes() as $cod => $rotulo)
                    <option value="{{ $cod }}" @selected($filtros['certificado'] === $cod)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="busca">Buscar (UTS ou fornecedor)</label>
            <input type="search" id="busca" name="busca" value="{{ $filtros['busca'] }}" placeholder="Ex: UTS-2026-001">
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        @php
            // "Situação" tem um padrão (estoque definitivo), então só conta
            // como filtro ativo quando o usuário muda para outra opção.
            $temFiltro = array_filter(\Illuminate\Support\Arr::except($filtros, 'situacao')) !== []
                || $filtros['situacao'] !== \App\Http\Controllers\Compras\DashboardController::SITUACAO_PADRAO;
        @endphp
        @if ($temFiltro)
            <a href="{{ route('relatorio.index') }}" class="btn btn-ghost">Limpar</a>
        @endif

        @auth
            @if (auth()->user()->hasRole('admin', 'compras'))
                <button type="submit" formaction="{{ route('relatorio.link') }}" formmethod="POST" class="btn btn-ghost" style="margin-left:auto;"
                        title="O link compartilhável não inclui a quebra por armazém.">
                    Gerar link compartilhável (7 dias)
                </button>
            @endif
        @endauth
    </form>

    {{-- O que existe mas está fora da tabela. Nunca some em silêncio: um
         estoque que subnotifica sem avisar é pior do que estoque nenhum. --}}
    @if ($pendentes && $pendentes['aguardando_compras'] > 0)
        <div class="alert alert-error" style="display:flex; align-items:baseline; gap:10px; flex-wrap:wrap;">
            <strong>Fora do estoque:</strong>
            <span>
                {{ number_format($pendentes['aguardando_sacas'], 2, ',', '.') }} sc em
                {{ $pendentes['aguardando_compras'] }}
                {{ \Illuminate\Support\Str::plural('compra', $pendentes['aguardando_compras']) }}
                aguardando o nº do lote do armazém.
            </span>
            <a href="{{ route('compras.index', ['pendencia' => 'sem_lote']) }}" style="margin-left:auto; font-size:13px;">
                Ver e informar os lotes →
            </a>
        </div>
    @endif

    @if ($pendentes && $pendentes['sem_classificacao_compras'] > 0)
        <div class="alert" style="background:#FCF3DC; color:#8A6116; border:1px solid #EBD9A8; display:flex; align-items:baseline; gap:10px; flex-wrap:wrap;">
            <strong>Em estoque, sem classificação:</strong>
            <span>
                {{ number_format($pendentes['sem_classificacao_sacas'], 2, ',', '.') }} sc em
                {{ $pendentes['sem_classificacao_compras'] }}
                {{ \Illuminate\Support\Str::plural('compra', $pendentes['sem_classificacao_compras']) }}
                com lote, mas sem distribuição de peneiras — não entram na tabela abaixo.
            </span>
            <a href="{{ route('compras.index', ['pendencia' => 'sem_classificacao']) }}" style="margin-left:auto; font-size:13px;">
                Classificar →
            </a>
        </div>
    @endif

    <div class="card">
        <div class="card__header card__header--dark mesh-texture">
            <h2>
                @switch ($filtros['situacao'])
                    @case ('aguardando') Aguardando nº do lote — ainda fora do estoque @break
                    @case ('todos') Comprado (estoque + aguardando lote) @break
                    @default Estoque definitivo — por armazém e peneira
                @endswitch
            </h2>
        </div>
        <div class="card__body" style="padding:0;">
            @include('dashboard._tabela-estoque')
        </div>
    </div>

    <p style="color:var(--muted); font-size:12.5px;">
        Só entra no estoque definitivamente a compra com o <strong>número do lote</strong> informado
        pelo armazém. Os totais acima são de <strong>entrada</strong> em estoque — o sistema ainda não
        registra embarque/faturamento, então não representam saldo disponível para venda.
        Esta tela é somente leitura: para alterar uma classificação, acesse a compra em
        <a href="{{ route('compras.index') }}">Compras lançadas</a>.
    </p>
@endsection
