@extends('layouts.app')

@section('title', 'Gestão de usuários')
@section('subtitle', 'Painel restrito. Somente administradores criam e alteram acessos.')

@section('crumb')
    <span>Administração</span><span class="sep">/</span><b>Usuários</b>
@endsection

@section('page_actions')
    <a href="#novo-usuario" class="btn-coffee">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5.5v13M5.5 12h13"></path></svg>
        <span>Novo usuário interno</span>
    </a>
@endsection

@section('content')
    <div class="notice-danger">
        <span class="notice-danger__icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="9.5" rx="2.5"></rect><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"></path></svg>
        </span>
        <div>
            <b>Cadastro público desativado</b>
            <p>Não existe formulário de registro aberto. Todo acesso é criado internamente por um administrador e vinculado a um perfil.</p>
        </div>
    </div>

    <div class="admin-grid">

        {{-- Tabela de usuários --}}
        <div class="usercard">
            <div class="utable__head">
                <span>Usuário</span>
                <span>Perfil de acesso</span>
                <span>Último acesso</span>
                <span class="r">Status</span>
            </div>

            @forelse ($users as $u)
                @php
                    $ini = \Illuminate\Support\Str::of($u->name)->explode(' ')->filter()
                        ->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
                    $slug = $u->role?->slug;
                    $avatarMod = ! $u->active ? ' uavatar--muted' : ($slug === 'admin' ? '' : ' uavatar--brown');
                    $pillMod = $slug === 'admin' ? '' : ($slug === 'diretoria' ? ' rolepill--muted' : ' rolepill--brown');
                @endphp
                <div class="utable__row">
                    <div class="utable__user">
                        <span class="uavatar{{ $avatarMod }}">{{ $ini }}</span>
                        <span style="min-width:0;">
                            <span class="utable__name">{{ $u->name }}</span><br>
                            <span class="utable__email">{{ $u->email }}</span>
                        </span>
                    </div>

                    <span style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                        <span class="rolepill{{ $pillMod }}">{{ $u->role?->nome ?? '—' }}</span>
                        @if ($u->force_password_change)
                            <span style="font-size:11px; color:#B08A2E;">senha pendente</span>
                        @endif
                    </span>

                    <span class="utable__last">{{ $u->last_login_at?->format('d/m/Y H:i') ?? '—' }}</span>

                    <span class="ustatus">
                        <span class="d {{ $u->active ? 'd--on' : 'd--off' }}"></span>
                        {{ $u->active ? 'Ativo' : 'Suspenso' }}
                    </span>

                    <div class="utable__rowacts">
                        <a href="{{ route('admin.users.edit', $u) }}" class="mini">Editar</a>
                        <form method="POST" action="{{ route('admin.users.reset-password', $u) }}"
                              onsubmit="return confirm('Gerar nova senha temporária para {{ $u->name }}?');">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="mini">Resetar senha</button>
                        </form>
                    </div>
                </div>
            @empty
                <div style="padding:28px 22px; text-align:center; color:var(--muted);">Nenhum usuário cadastrado.</div>
            @endforelse

            <div class="usercard__foot">
                <span>{{ $users->total() }} {{ \Illuminate\Support\Str::plural('usuário', $users->total()) }}
                    @if ($users->where('active', false)->count()) · {{ $users->where('active', false)->count() }} suspenso(s) nesta página @endif
                </span>
                <span class="mono">ACESSO RESTRITO</span>
            </div>
        </div>

        {{-- Formulário: adicionar usuário interno --}}
        <form method="POST" action="{{ route('admin.users.store') }}" class="userform" id="novo-usuario">
            @csrf
            <div>
                <h2>Adicionar usuário interno</h2>
                <p class="userform__lead">Uma senha provisória é gerada e o usuário é obrigado a trocá-la no primeiro acesso.</p>
            </div>

            <div class="fields">
                <label>
                    <span class="lbl">Nome completo</span>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Nome do colaborador" required>
                </label>
                <label>
                    <span class="lbl">E-mail corporativo</span>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="nome@utrading.com.br" required>
                </label>
                <label>
                    <span class="lbl">Perfil de acesso</span>
                    <select name="role_id" id="role_id" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected(old('role_id') == $role->id) data-desc="{{ $role->descricao }}">{{ $role->nome }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="roledesc" id="roledesc">
                <b>Permissões do perfil.</b> Selecione um perfil para ver o que ele libera.
            </div>

            <button type="submit" class="btn-coffee" style="margin-top:2px;">
                <span>Criar acesso</span>
                <span class="bean">
                    <svg viewBox="0 0 24 32" width="15" height="20"><ellipse cx="12" cy="16" rx="10.5" ry="15" fill="#E9E2D1"></ellipse><path d="M12 2.5c3.2 6 3.2 21.5 0 27" stroke="#0A3A22" stroke-width="2.2" fill="none"></path></svg>
                </span>
            </button>
        </form>
    </div>

    @if ($users->hasPages())
        <div class="pagination" style="margin-top:20px;">{{ $users->links() }}</div>
    @endif

    <div style="padding:22px 0 4px; font-size:11.5px; color:rgba(11,61,36,.42);">
        Union Trading · Controle de compras de café · acesso restrito a usuários internos
    </div>

    <script>
        (function () {
            var sel = document.getElementById('role_id');
            var box = document.getElementById('roledesc');
            if (!sel || !box) return;
            function update() {
                var opt = sel.options[sel.selectedIndex];
                var desc = opt ? opt.getAttribute('data-desc') : '';
                box.innerHTML = desc
                    ? '<b>' + opt.text + '.</b> ' + desc
                    : '<b>Permissões do perfil.</b> Selecione um perfil para ver o que ele libera.';
            }
            sel.addEventListener('change', update);
            if (sel.value) update();
        })();
    </script>
@endsection
