<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Entrar — Union Trading</title>
    @include('partials.styles')
</head>
<body>
<div class="login-page">

    {{-- Cena de fundo: vapor desfocado + grãos de café caindo (puro CSS/SVG, sem JS) --}}
    <div class="login-fx" aria-hidden="true">
        <div class="login-steam">
            <i style="left:14%; bottom:-80px;  width:220px; height:300px; background:rgba(233,226,209,.30); animation:ut-steam 13s ease-in-out infinite;"></i>
            <i style="left:40%; bottom:-120px; width:260px; height:340px; background:rgba(122,214,163,.22); animation:ut-steam 17s ease-in-out 3s infinite;"></i>
            <i style="left:64%; bottom:-60px;  width:200px; height:280px; background:rgba(196,138,84,.26); animation:ut-steam 15s ease-in-out 6s infinite;"></i>
            <i style="left:82%; bottom:-140px; width:300px; height:360px; background:rgba(233,226,209,.20); animation:ut-steam 19s ease-in-out 9s infinite;"></i>
            <i style="left:4%;  top:20%; width:340px; height:340px; background:rgba(10,53,33,.5);  animation:ut-drift 24s ease-in-out infinite;"></i>
            <i style="right:6%; top:8%;  width:300px; height:300px; background:rgba(59,36,21,.55); animation:ut-drift 28s ease-in-out 4s infinite;"></i>
        </div>

        @php
            // posição (left %), largura px, duração s, atraso s (NEGATIVO p/ já iniciar
            // no meio da queda), opacidade, cor do grão
            $graos = [
                [3,  24, 11,   -2,  .85, '#6B4327'],
                [10, 18, 15,   -9,  .6,  '#5C3720'],
                [19, 30, 13,   -5,  .9,  '#7A4E2E'],
                [27, 20, 17,   -13, .55, '#6B4327'],
                [36, 26, 12,   -7,  .85, '#5C3720'],
                [45, 16, 19,   -3,  .5,  '#7A4E2E'],
                [54, 30, 14,   -11, .9,  '#4A2C1A'],
                [63, 20, 16,   -1,  .6,  '#6B4327'],
                [72, 27, 12.5, -8,  .85, '#5C3720'],
                [81, 22, 18,   -15, .7,  '#7A4E2E'],
                [90, 19, 15,   -6,  .6,  '#6B4327'],
            ];
        @endphp
        @foreach ($graos as [$left, $w, $dur, $delay, $op, $cor])
            <div class="login-bean" style="left:{{ $left }}%; width:{{ $w }}px; opacity:{{ $op }}; animation:ut-fall {{ $dur }}s linear {{ $delay }}s infinite;">
                <svg viewBox="0 0 24 32" width="{{ $w }}" height="{{ round($w * 1.33) }}">
                    <ellipse cx="12" cy="16" rx="10.5" ry="15" fill="{{ $cor }}"></ellipse>
                    <path d="M12 2.5c3.2 6 3.2 21.5 0 27" stroke="#22120A" stroke-width="1.7" fill="none"></path>
                </svg>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('login') }}" class="login-card" novalidate>
        @csrf

        <div class="login-head">
            <div class="login-brandtile">
                @include('partials.logo-union')
            </div>
            <div>
                <h1 class="login-title">Bem-vindo de volta</h1>
                <p class="login-sub">Acesse sua conta Union Trading</p>
            </div>
        </div>

        @if (session('status'))
            <div class="login-alert login-alert--ok">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="login-alert">
                @foreach ($errors->all() as $erro)
                    <div>{{ $erro }}</div>
                @endforeach
            </div>
        @endif

        <div class="login-fields">
            <label class="login-field">
                <span class="lbl">E-mail</span>
                <span class="login-inputwrap {{ $errors->has('email') ? 'has-error' : '' }}">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="rgba(251,248,241,.6)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5.5" width="18" height="13" rx="2.5"></rect><path d="m4 7 8 6 8-6"></path></svg>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="nome@utrading.com.br" required autofocus autocomplete="username">
                </span>
            </label>

            <label class="login-field">
                <span class="lbl">Senha</span>
                <span class="login-inputwrap {{ $errors->has('password') ? 'has-error' : '' }}">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="rgba(251,248,241,.6)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="9.5" rx="2.5"></rect><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"></path></svg>
                    <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                </span>
            </label>
        </div>

        <div class="login-row">
            <label class="login-check">
                <input type="checkbox" name="remember" @checked(old('remember'))>
                <span class="box">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#F7F3EA" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"></path></svg>
                </span>
                <span>Manter-me conectado</span>
            </label>
            <a href="{{ route('password.request') }}" class="login-forgot">Esqueci minha senha</a>
        </div>

        <button type="submit" class="login-btn">
            <span>Entrar</span>
            <span class="bean">
                <svg viewBox="0 0 24 32" width="16" height="21"><ellipse cx="12" cy="16" rx="10.5" ry="15" fill="#E9E2D1"></ellipse><path d="M12 2.5c3.2 6 3.2 21.5 0 27" stroke="#0A3A22" stroke-width="2.2" fill="none"></path></svg>
            </span>
        </button>

        <p class="login-foot">Acessos criados somente por administradores · Sem cadastro público</p>
    </form>
</div>
</body>
</html>
