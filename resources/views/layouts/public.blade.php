<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Sem meta csrf-token de propósito: esta página não tem formulários. --}}
    <title>@yield('title', 'Union Trading — Relatório')</title>
    @include('partials.styles')
</head>
<body style="background:var(--bg);">
    <header style="background:var(--primary-dark); padding:18px 28px;" class="mesh-texture">
        <span style="font-family:var(--font-display); font-size:20px; color:#fff; letter-spacing:.06em;">UNION</span>
        <span style="font-size:11px; letter-spacing:.18em; text-transform:uppercase; color:#B7CFC1; margin-left:8px;">Trading</span>
    </header>

    <main style="max-width:1000px; margin:0 auto; padding:28px 20px 60px;">
        <div class="alert" style="background:#EEEBE1; color:var(--muted); border:1px solid var(--border);">
            Visualização pública e temporária, somente leitura. Este link expira automaticamente.
        </div>
        @yield('content')
    </main>
</body>
</html>
