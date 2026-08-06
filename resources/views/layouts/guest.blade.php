<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Union Trading')</title>
    @include('partials.styles')
</head>
<body>
    <div class="guest-shell">
        <div class="guest-card">
            <div class="guest-card__brand">
                <div class="brand-u">UNION</div>
                <div class="brand-sub">Trading</div>
            </div>

            @include('partials.flash')

            @yield('content')
        </div>
    </div>
</body>
</html>
