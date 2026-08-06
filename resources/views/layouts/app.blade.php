<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Token CSRF lido pelo JS caso algum fetch() precise dele; todo <form> já inclui @csrf --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Union Trading')</title>
    @include('partials.styles')
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar__brand mesh-texture">
                <div class="brand-u">UNION</div>
                <div class="brand-sub">Trading</div>
            </div>

            <nav class="sidebar__nav">
                <a href="{{ route('dashboard') }}" class="sidebar__link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                    Início
                </a>

                @auth
                    @if (auth()->user()->hasRole('admin', 'compras', 'diretoria'))
                        <div class="sidebar__group-label">Compras &amp; Classificação</div>
                        @if (auth()->user()->hasRole('admin', 'compras'))
                            <a href="{{ route('compras.create') }}" class="sidebar__link {{ request()->routeIs('compras.create') ? 'is-active' : '' }}">Nova compra</a>
                            <a href="{{ route('compras.index') }}" class="sidebar__link {{ request()->routeIs('compras.index') || request()->routeIs('compras.show') ? 'is-active' : '' }}">Compras lançadas</a>
                        @endif
                        <a href="{{ route('relatorio.index') }}" class="sidebar__link {{ request()->routeIs('relatorio.index') ? 'is-active' : '' }}">Relatório (dashboard)</a>
                    @endif

                    @if (auth()->user()->hasRole('admin'))
                        <div class="sidebar__group-label">Administração</div>
                        <a href="{{ route('admin.users.index') }}" class="sidebar__link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">Usuários</a>
                    @endif
                @endauth
            </nav>

            <div class="sidebar__footer">
                @auth
                    <div class="sidebar__user">{{ auth()->user()->name }}</div>
                    <div class="sidebar__role">{{ auth()->user()->role?->nome }}</div>
                    <div style="display:flex; gap:10px; margin-top:6px;">
                        <a href="{{ route('senha.trocar.form') }}" class="sidebar__logout" style="text-decoration:underline;">Trocar senha</a>
                        <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="sidebar__logout">Sair</button>
                        </form>
                    </div>
                @endauth
            </div>
        </aside>

        <div class="main">
            <header class="topbar">
                <h1>@yield('title', 'Union Trading')</h1>
            </header>
            <div class="content">
                @include('partials.flash')
                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
