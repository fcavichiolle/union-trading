@extends('layouts.guest')

@section('title', 'Redefinir senha')

@section('content')
    <h1>Defina uma nova senha</h1>

    <form method="POST" action="{{ route('password.store') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field {{ $errors->has('email') ? 'has-error' : '' }}" style="margin-bottom:14px;">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autofocus>
            @error('email') <div class="field-error">{{ $message }}</div> @enderror
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

        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Redefinir senha</button>
    </form>
@endsection
