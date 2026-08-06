@extends('layouts.app')

@section('title', 'Novo usuário')

@section('content')
    <div class="card" style="max-width:640px;">
        <div class="card__header"><h2>Novo usuário</h2></div>
        <div class="card__body">
            <p style="color:var(--muted); font-size:13.5px; margin-top:0;">
                Deixe a senha em branco para o sistema gerar uma senha temporária forte
                automaticamente (mostrada uma única vez após salvar). O usuário será
                obrigado a trocá-la no primeiro acesso.
            </p>

            <form method="POST" action="{{ route('admin.users.store') }}" novalidate>
                @csrf

                <div class="form-grid">
                    <div class="field field--full {{ $errors->has('name') ? 'has-error' : '' }}">
                        <label for="name">Nome completo</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                        @error('name') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field field--full {{ $errors->has('email') ? 'has-error' : '' }}">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required>
                        @error('email') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('role_id') ? 'has-error' : '' }}">
                        <label for="role_id">Perfil de acesso (setor)</label>
                        <select id="role_id" name="role_id" required>
                            <option value="">Selecione...</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->nome }}</option>
                            @endforeach
                        </select>
                        @error('role_id') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('password') ? 'has-error' : '' }}">
                        <label for="password">Senha (opcional)</label>
                        <input type="password" id="password" name="password" autocomplete="new-password">
                        @error('password') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label for="password_confirmation">Confirmar senha</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
                    </div>
                </div>

                <div style="margin-top:20px; display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary">Criar usuário</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
