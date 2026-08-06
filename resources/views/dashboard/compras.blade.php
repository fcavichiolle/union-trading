@extends('layouts.app')

@section('title', 'Relatório de classificação')

@section('content')
    <form method="GET" action="{{ route('relatorio.index') }}" class="filter-bar">
        <div class="field">
            <label for="mes">Mês da entrega</label>
            <input type="month" id="mes" name="mes" value="{{ $mesFiltro }}">
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        @if ($mesFiltro !== '')
            <a href="{{ route('relatorio.index') }}" class="btn btn-ghost">Limpar</a>
        @endif
@extends('layouts.app')

@section('title', 'Relatório de classificação')

@section('content')
    <form method="GET" action="{{ route('relatorio.index') }}" class="filter-bar">
        <div class="field">
            <label for="mes">Mês da entrega</label>
            <input type="month" id="mes" name="mes" value="{{ $mesFiltro }}">
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        @if ($mesFiltro !== '')
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

    <div class="card">
        <div class="card__header card__header--dark mesh-texture">
            <h2>Distribuição por certificação — somente leitura</h2>
        </div>
        <div class="card__body" style="padding:0;">
            @include('dashboard._tabela-certificacao')
        </div>
    </div>

    <p style="color:var(--muted); font-size:12.5px;">
        Esta tela é somente leitura — não existe nenhum formulário de edição aqui.
        Para alterar uma classificação, acesse a compra correspondente em "Compras lançadas".
    </p>
@endsection
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
