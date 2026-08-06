@extends('layouts.app')

@section('title', 'Trocar senha')

@section('content')
    <div class="card" style="max-width:480px;">
        <div class="card__header"><h2>Alterar senha</h2></div>
        <div class="card__body">
            @if ($obrigatorio)
                <div class="alert alert-error">
                    Por segurança, você precisa definir uma nova senha antes de continuar
                    (esta conta foi criada com uma senha temporária).
                </div>
            @endif

            <form method="POST" action="{{ route('senha.trocar.update') }}" novalidate>
                @csrf
                @method('PUT')

                <div class="field {{ $errors->has('current_password') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                    <label for="current_password">Senha atual</label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    @error('current_password') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <div class="field {{ $errors->has('password') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                    <label for="password">Nova senha</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                    <span class="hint">Mínimo 12 caracteres, com maiúsculas, minúsculas, números e símbolos.</span>
                    @error('password') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <div class="field" style="margin-bottom:18px;">
                    <label for="password_confirmation">Confirme a nova senha</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary">Salvar nova senha</button>
            </form>
        </div>
    </div>
@endsection
