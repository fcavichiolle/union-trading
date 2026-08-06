@extends('layouts.app')

@section('title', 'Início')

@section('content')
    <p style="color:var(--muted); margin-top:-6px;">Bem-vindo(a), {{ $user->name }}.</p>

    <div class="form-grid--3" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); gap:16px; margin-top:18px;">
        @if ($user->hasRole('admin', 'compras'))
            <a href="{{ route('compras.create') }}" class="card" style="text-decoration:none; padding:20px;">
                <h2 style="font-size:16px;">Nova compra</h2>
                <p style="color:var(--muted); font-size:13.5px; margin:0;">Registrar entrada de compra (UTS, fornecedor, volume).</p>
            </a>
            <a href="{{ route('compras.index') }}" class="card" style="text-decoration:none; padding:20px;">
                <h2 style="font-size:16px;">Compras lançadas</h2>
                <p style="color:var(--muted); font-size:13.5px; margin:0;">Ver, classificar e lançar financeiro das compras.</p>
            </a>
        @endif

        @if ($user->hasRole('admin', 'compras', 'diretoria'))
            <a href="{{ route('relatorio.index') }}" class="card" style="text-decoration:none; padding:20px;">
                <h2 style="font-size:16px;">Relatório de classificação</h2>
                <p style="color:var(--muted); font-size:13.5px; margin:0;">Dashboard somente leitura, agrupado por padrão e peneira.</p>
            </a>
        @endif

        @if ($user->hasRole('admin'))
            <a href="{{ route('admin.users.index') }}" class="card" style="text-decoration:none; padding:20px;">
                <h2 style="font-size:16px;">Gestão de usuários</h2>
                <p style="color:var(--muted); font-size:13.5px; margin:0;">Criar e administrar contas e perfis de acesso.</p>
            </a>
        @endif
    </div>
@endsection
