<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usuários criados pelo admin recebem uma senha temporária e
 * force_password_change = true. Esse middleware trava o acesso ao
 * resto do sistema até a senha ser trocada, evitando que uma senha
 * "provisória" conhecida por terceiros (quem criou o usuário) continue
 * válida indefinidamente.
 */
class RedirectIfPasswordChangeRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->force_password_change && ! $request->routeIs('senha.trocar*', 'logout')) {
            return redirect()->route('senha.trocar.form');
        }

        return $next($request);
    }
}
