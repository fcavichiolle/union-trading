<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Union Trading')</title>
    @include('partials.styles')
    {{-- Aplica o tema salvo antes da pintura, evitando "flash" de tela clara. --}}
    <script>(function(){try{if(localStorage.getItem('ut-theme')==='dark')document.documentElement.dataset.theme='dark';}catch(e){}})();</script>
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
                            @isset($badgesMenu['compras.index'])
                                <span class="sb-badge" title="Compras com etapa pendente">{{ $badgesMenu['compras.index'] }}</span>
                            @endisset
                        </a>
                    @endif
                    <a href="{{ route('relatorio.index') }}" class="sb-link {{ request()->routeIs('relatorio.index') ? 'is-active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16"></path><rect x="6" y="11" width="3.2" height="6"></rect><rect x="11.4" y="7" width="3.2" height="10"></rect><rect x="16.8" y="13.5" width="3.2" height="3.5"></rect></svg>
                        <span>Estoque</span>
                    </a>
                </div>
            @endif

            @if ($u->hasRole('admin', 'compras'))
                <div class="sb-group">
                    <div class="sb-group__label">Contratos</div>
                    <a href="{{ route('contratos.create') }}" class="sb-link {{ request()->routeIs('contratos.create') ? 'is-active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3.5H7a2 2 0 0 0-2 2V19a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.5Z"></path><path d="M14 3.5V8.5h5"></path><path d="M12 12v5M9.5 14.5h5"></path></svg>
                        <span>Novo contrato</span>
                    </a>
                    <a href="{{ route('contratos.index') }}" class="sb-link {{ request()->routeIs('contratos.index') || request()->routeIs('contratos.show') ? 'is-active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3.5H7a2 2 0 0 0-2 2V19a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.5Z"></path><path d="M14 3.5V8.5h5"></path><path d="M8.5 12.5h7M8.5 16h5"></path></svg>
                        <span>Contratos gerados</span>
                    </a>
                </div>
            @endif

            <div class="sb-group">
                <div class="sb-group__label">Mercado</div>
                @if ($u->hasRole('admin', 'compras'))
                    <a href="{{ route('ny.index') }}" class="sb-link {{ request()->routeIs('ny.index') ? 'is-active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4v4M7 14v6M7 8h0a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h0a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2Z"></path><path d="M17 4v2M17 16v4M17 6h0a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h0a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Z"></path></svg>
                        <span>Tela NY (fixações)</span>
                        @isset($badgesMenu['ny.index'])
                            <span class="sb-badge" title="Lotes a fixar">{{ $badgesMenu['ny.index'] }}</span>
                        @endisset
                    </a>
                @endif
                <a href="{{ route('mercado.index') }}" class="sb-link {{ request()->routeIs('mercado.index') ? 'is-active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16"></path><path d="m5 14 4-4 3.5 3L18 7"></path><path d="M14.5 7H18v3.5"></path></svg>
                    <span>Cotações</span>
                </a>
            </div>

            {{-- Dois grupos de propósito: CADASTROS são coisas do negócio que
                 alimentam os formulários; ADMINISTRAÇÃO mexe em quem entra no
                 sistema. Misturar "Usuários" com "Qualidades" fazia a lista
                 parecer um saco de coisas soltas. --}}
            @if ($u->hasRole('admin'))
                <div class="sb-group">
                    <div class="sb-group__label">Cadastros</div>
                    <a href="{{ route('admin.clientes.index') }}" class="sb-link {{ request()->routeIs('admin.clientes.*') ? 'is-active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V9l8-5 8 5v11"></path><path d="M9 20v-6h6v6"></path></svg>
                        <span>Clientes</span>
                    </a>
                    <a href="{{ route('admin.armazens.index') }}" class="sb-link {{ request()->routeIs('admin.armazens.*') ? 'is-active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 4l9 6.5"></path><path d="M5 9.5V20h14V9.5"></path><path d="M8.5 20v-6h7v6"></path><path d="M8.5 14h7"></path></svg>
                        <span>Armazéns</span>
                    </a>
                    <a href="{{ route('admin.corretoras.index') }}" class="sb-link {{ request()->routeIs('admin.corretoras.*') ? 'is-active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 20.5v-13l5-3.5 5 3.5v13"></path><path d="M13.5 20.5h7v-8.5l-3.5-2.5-3.5 2.5"></path><path d="M6.5 11h2M6.5 14.5h2M6.5 18h2M16.5 14.5h1.5M16.5 18h1.5"></path></svg>
                        <span>Corretoras</span>
                    </a>
                    <a href="{{ route('admin.qualidades.index') }}" class="sb-link {{ request()->routeIs('admin.qualidades.*') ? 'is-active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m12 4 2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4L4.2 9.7l5.4-.8Z"></path></svg>
                        <span>Qualidades</span>
                    </a>
                </div>

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
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="Alternar modo escuro" title="Alternar modo escuro">
                    <svg class="i-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4.5"></circle><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"></path></svg>
                    <svg class="i-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"></path></svg>
                </button>
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

<script>
    (function () {
        var btn = document.getElementById('themeToggle');
        if (!btn) return;
        function sync() { btn.setAttribute('aria-pressed', document.documentElement.dataset.theme === 'dark' ? 'true' : 'false'); }
        btn.addEventListener('click', function () {
            var root = document.documentElement;
            if (root.dataset.theme === 'dark') {
                delete root.dataset.theme;
                try { localStorage.setItem('ut-theme', 'light'); } catch (e) {}
            } else {
                root.dataset.theme = 'dark';
                try { localStorage.setItem('ut-theme', 'dark'); } catch (e) {}
            }
            sync();
        });
        sync();
    })();
</script>

<script>
    // Botões ".js-save-pdf": abre um "Salvar como" nativo (escolher a pasta)
    // em vez de jogar direto no Downloads. Sem suporte (ou fora de contexto
    // seguro), o link segue o comportamento normal de download.
    (function () {
        if (!window.showSaveFilePicker) return;
        document.addEventListener('click', async function (ev) {
            var a = ev.target.closest ? ev.target.closest('.js-save-pdf') : null;
            if (!a) return;
            ev.preventDefault();
            var url = a.getAttribute('href');
            var nome = a.getAttribute('data-filename') || 'contrato.pdf';
            try {
                var resp = await fetch(url, { headers: { 'Accept': 'application/pdf' } });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                var blob = await resp.blob();
                var handle = await window.showSaveFilePicker({
                    suggestedName: nome,
                    types: [{ description: 'PDF', accept: { 'application/pdf': ['.pdf'] } }]
                });
                var w = await handle.createWritable();
                await w.write(blob);
                await w.close();
            } catch (e) {
                if (e && e.name === 'AbortError') return; // usuário cancelou o diálogo
                window.location.href = url;                // fallback: download normal
            }
        });
    })();
</script>
</body>
</html>
