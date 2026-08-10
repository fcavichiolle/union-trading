@extends('layouts.app')

@section('title', 'Relatório de classificação')

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
        @if (array_filter($filtros) !== [])
            <a href="{{ route('relatorio.index') }}" class="btn btn-ghost">Limpar</a>
        @endif

        @auth
            @if (auth()->user()->hasRole('admin', 'compras'))
                <button type="submit" formaction="{{ route('relatorio.link') }}" formmethod="POST" class="btn btn-ghost" style="margin-left:auto;">
                    Gerar link compartilhável (7 dias)
                </button>
            @endif
        @endauth
    </form>

    <div class="card">
        <div class="card__header card__header--dark mesh-texture">
            <h2>Distribuição por padrão e peneira — somente leitura</h2>
        </div>
        <div class="card__body" style="padding:0;">
            @include('dashboard._tabela-classificacao')
        </div>
    </div>

    <p style="color:var(--muted); font-size:12.5px;">
        Esta tela é somente leitura — não existe nenhum formulário de edição aqui.
        Para alterar uma classificação, acesse a compra correspondente em "Compras lançadas".
    </p>
@endsection
