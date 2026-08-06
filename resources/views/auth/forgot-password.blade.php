@extends('layouts.guest')

@section('title', 'Esqueci minha senha')

@section('content')
    <h1>Recuperar acesso</h1>
    <p style="font-size:13.5px; color:var(--muted); margin-top:-8px; margin-bottom:18px;">
        Informe seu e-mail. Se ele estiver cadastrado, enviaremos um link para redefinir sua senha.
    </p>

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <div class="field {{ $errors->has('email') ? 'has-error' : '' }}" style="margin-bottom:18px;">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email') <div class="field-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Enviar link de redefinição</button>
    </form>

    <div class="guest-links">
        <a href="{{ route('login') }}">Voltar para o login</a>
    </div>
@endsection
