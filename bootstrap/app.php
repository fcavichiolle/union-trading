<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RedirectIfPasswordChangeRequired;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Aliases usados nas rotas (routes/web.php) com ->middleware('role:admin')
        // etc. Registrar aqui é o equivalente, no Laravel 12/13, ao antigo
        // $routeMiddleware do app/Http/Kernel.php das versões anteriores.
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'conta.ativa' => EnsureUserIsActive::class,
            'senha.pendente' => RedirectIfPasswordChangeRequired::class,
        ]);

        // Cabeçalhos de segurança em toda resposta (web + api).
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
