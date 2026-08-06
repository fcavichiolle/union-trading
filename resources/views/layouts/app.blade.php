<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Union Trading')</title>
    @include('partials.styles')
</head>
<body>
@php
    $u = auth()->user();
    $iniciais = collect(preg_split('/\s+/', trim($u->name)))
        ->filter()
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->values();
    $iniciais = $iniciais->count() > 1
        ? $iniciais->first() . $iniciais->last()
        : mb_strtoupper(mb_substr(trim($u->name), 0, 2));
@endphp
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar__logo">
            @include('partials.logo-union')
        </div>

        <nav class="sidebar__nav">
            <a href="{{ route('dashboard') }}" class="sb-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3.5l9 7"></path><path d="M5.5 9.5V20h13V9.5"></path><path d="M9.75 20v-5.5h4.5V20"></path></svg>
                <span>Início</span>
            </a>

            @if ($u->hasRole('admin', 'compras', 'diretoria'))
                <div class="sb-group">
                    <div class="sb-group__label">Compras &amp; Classificação</div>
                    @if ($u->hasRole('admin', 'compras'))
                        <a href="{{ route('compras.create') }}" class="sb-link {{ request()->routeIs('compras.create') ? 'is-active' : '' }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M12 5.5v13M5.5 12h13"></path></svg>
                            <span>Nova compra</span>
                        </a>
                        <a href="{{ route('compras.index') }}" class="sb-link {{ request()->routeIs('compras.index') || request()->routeIs('compras.show') ? 'is-active' : '' }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="3.5" width="15" height="17" rx="2"></rect><path d="M8.5 8h7M8.5 12h7M8.5 16h4"></path></svg>
                            <span>Compras lançadas</span>
                        </a>
                    @endif
                    <a href="{{ route('relatorio.index') }}" class="sb-link {{ request()->routeIs('relatorio.index') ? 'is-active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16"></path><rect x="6" y="11" width="3.2" height="6"></rect><rect x="11.4" y="7" width="3.2" height="10"></rect><rect x="16.8" y="13.5" width="3.2" height="3.5"></rect></svg>
                        <span>Relatório (dashboard)</span>
                    </a>
                </div>
            @endif

            @if ($u->hasRole('admin'))
                <div class="sb-group">
                    <div class="sb-group__label">Administração</div>
                    <a href="{{ route('admin.users.index') }}" class="sb-link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8.5" r="3.5"></circle><path d="M5.5 19.5c1.3-3.2 3.8-4.8 6.5-4.8s5.2 1.6 6.5 4.8"></path></svg>
                        <span>Usuários</span>
                    </a>
                </div>
            @endif
        </nav>

        <div class="sidebar__foot">
            <span class="dot"></span>
            <span>Sistema operacional</span>
        </div>
    </aside>

    <div class="main">
        <header class="appbar">
            <div class="appbar__crumb">
                @hasSection('crumb')
                    @yield('crumb')
                @endif
            </div>
            <div class="appbar__user">
                <div class="avatar">{{ $iniciais }}</div>
                <div class="appbar__meta">
                    <span class="nm">{{ $u->name }}</span>
                    <span class="rl">{{ $u->role?->nome ?? 'Union Trading' }}</span>
                </div>
                <div class="appbar__acts">
                    <a href="{{ route('senha.trocar.form') }}" title="Trocar senha">Trocar senha</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" title="Sair">Sair</button>
                    </form>
                </div>
            </div>
        </header>

        <div class="content">
            <div class="page-head">
                <div>
                    <h1>@yield('title', 'Union Trading')</h1>
                    @hasSection('subtitle')<p class="page-sub">@yield('subtitle')</p>@endif
                </div>
                @hasSection('page_actions')
                    <div class="page-actions">@yield('page_actions')</div>
                @endif
            </div>

            @include('partials.flash')
            @yield('content')
        </div>
    </div>
</div>
</body>
</html>
