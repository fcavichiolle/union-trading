@extends('layouts.guest')

@section('title', 'Entrar')

@section('content')
    <h1>Acesse sua conta</h1>

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="field {{ $errors->has('email') ? 'has-error' : '' }}" style="margin-bottom:14px;">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <div class="field {{ $errors->has('password') ? 'has-error' : '' }}" style="margin-bottom:10px;">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>

        <label style="display:flex; align-items:center; gap:8px; font-size:13.5px; color:var(--muted); margin-bottom:18px;">
            <input type="checkbox" name="remember" style="width:auto;"> Manter conectado neste dispositivo
        </label>

        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Entrar</button>
    </form>

    <div class="guest-links">
        <a href="{{ route('password.request') }}">Esqueci minha senha</a>
    </div>
@endsection
