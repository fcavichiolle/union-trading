@extends('layouts.app')

@section('title', 'Início')
@section('subtitle', 'Bem-vindo, ' . $user->name . '.')

@section('content')
    <div class="home-cards">
        @if ($user->hasRole('admin', 'compras'))
            <a href="{{ route('compras.create') }}" class="home-card">
                <div class="home-card__icon">
                    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M12 5.5v13M5.5 12h13"></path></svg>
                </div>
                <div>
                    <h2>Nova compra</h2>
                    <p>Registrar uma nova aquisição de café.</p>
                </div>
            </a>
            <a href="{{ route('compras.index') }}" class="home-card">
                <div class="home-card__icon">
                    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="3.5" width="15" height="17" rx="2"></rect><path d="M8.5 8h7M8.5 12h7M8.5 16h4"></path></svg>
                </div>
                <div>
                    <h2>Compras lançadas</h2>
                    <p>Consultar e editar lançamentos existentes.</p>
                </div>
            </a>
        @endif

        @if ($user->hasRole('admin', 'compras', 'diretoria'))
            <a href="{{ route('relatorio.index') }}" class="home-card">
                <div class="home-card__icon">
                    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16"></path><rect x="6" y="11" width="3.2" height="6"></rect><rect x="11.4" y="7" width="3.2" height="10"></rect><rect x="16.8" y="13.5" width="3.2" height="3.5"></rect></svg>
                </div>
                <div>
                    <h2>Relatório de classificação</h2>
                    <p>Indicadores de qualidade por lote.</p>
                </div>
            </a>
        @endif

        @if ($user->hasRole('admin'))
            <a href="{{ route('admin.users.index') }}" class="home-card">
                <div class="home-card__icon">
                    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8.5" r="3.5"></circle><path d="M5.5 19.5c1.3-3.2 3.8-4.8 6.5-4.8s5.2 1.6 6.5 4.8"></path></svg>
                </div>
                <div>
                    <h2>Gestão de usuários</h2>
                    <p>Permissões e acessos da equipe.</p>
                </div>
            </a>
        @endif
    </div>

    <div class="home-foot">
        <span>Union Trading · Sistema de compras e classificação</span>
        <span class="mono">São Paulo, BR</span>
    </div>
@endsection
