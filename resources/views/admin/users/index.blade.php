@extends('layouts.app')

@section('title', 'Usuários')

@section('content')
    <div style="display:flex; justify-content:flex-end; margin-bottom:16px;">
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">+ Novo usuário</a>
    </div>

    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Perfil</th>
                    <th>Status</th>
                    <th>Último acesso</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td><span class="badge badge--green">{{ $u->role?->nome }}</span></td>
                        <td>
                            @if ($u->active)
                                <span class="badge badge--green">Ativo</span>
                            @else
                                <span class="badge badge--red">Desativado</span>
                            @endif
                            @if ($u->force_password_change)
                                <span class="badge badge--muted">Troca de senha pendente</span>
                            @endif
                        </td>
                        <td>{{ $u->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td style="white-space:nowrap;">
                            <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-ghost" style="padding:6px 12px; font-size:13px;">Editar</a>
                            <form method="POST" action="{{ route('admin.users.reset-password', $u) }}" style="display:inline;" onsubmit="return confirm('Gerar nova senha temporária para {{ $u->name }}?');">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-ghost" style="padding:6px 12px; font-size:13px;">Resetar senha</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center; color:var(--muted); padding:24px;">Nenhum usuário cadastrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">{{ $users->links() }}</div>
@endsection
