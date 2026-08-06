@extends('layouts.app')

@section('title', 'Editar usuário')

@section('content')
    <div class="card" style="max-width:640px;">
        <div class="card__header"><h2>Editar usuário</h2></div>
        <div class="card__body">
            <form method="POST" action="{{ route('admin.users.update', $user) }}" novalidate>
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="field field--full {{ $errors->has('name') ? 'has-error' : '' }}">
                        <label for="name">Nome completo</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field field--full {{ $errors->has('email') ? 'has-error' : '' }}">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                        @error('email') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('role_id') ? 'has-error' : '' }}">
                        <label for="role_id">Perfil de acesso (setor)</label>
                        <select id="role_id" name="role_id" required>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id) == $role->id)>{{ $role->nome }}</option>
                            @endforeach
                        </select>
                        @error('role_id') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label for="active">Status da conta</label>
                        <select id="active" name="active" required>
                            <option value="1" @selected(old('active', $user->active ? '1' : '0') == '1')>Ativo</option>
                            <option value="0" @selected(old('active', $user->active ? '1' : '0') == '0')>Desativado</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:20px; display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary">Salvar alterações</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
