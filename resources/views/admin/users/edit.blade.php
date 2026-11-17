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

                {{-- Assinatura usada no e-mail de instrução de AA. Na planilha
                     ela era fixa dentro da macro, e todo mundo mandava o e-mail
                     assinado com o mesmo nome. --}}
                <fieldset style="margin-top:22px; border:1px solid var(--border); border-radius:10px; padding:16px 18px;">
                    <legend style="padding:0 8px; font-size:12.5px; color:var(--muted); font-weight:600;">
                        Assinatura de e-mail (instruções de AA)
                    </legend>

                    <div class="form-grid">
                        <div class="field">
                            <label for="cargo">Cargo</label>
                            <input type="text" id="cargo" name="cargo" value="{{ old('cargo', $user->cargo) }}" placeholder="Ex.: Assistente de logística">
                            @error('cargo') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="field">
                            <label for="cargo_en">Cargo (inglês)</label>
                            <input type="text" id="cargo_en" name="cargo_en" value="{{ old('cargo_en', $user->cargo_en) }}" placeholder="Ex.: Logistics Assistant">
                            @error('cargo_en') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="field">
                            <label for="telefone">Telefone</label>
                            <input type="text" id="telefone" name="telefone" value="{{ old('telefone', $user->telefone) }}" placeholder="+55 00 0000-0000">
                            @error('telefone') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="field">
                            <label for="celular">Celular</label>
                            <input type="text" id="celular" name="celular" value="{{ old('celular', $user->celular) }}" placeholder="+55 (19) 90000-0000">
                            @error('celular') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <p style="margin:12px 0 0; font-size:12.5px; color:var(--muted);">
                        Campo em branco simplesmente não aparece na assinatura. O e-mail e
                        <code>www.utrading.com.br</code> entram sempre.
                    </p>
                </fieldset>

                <div style="margin-top:20px; display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary">Salvar alterações</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
