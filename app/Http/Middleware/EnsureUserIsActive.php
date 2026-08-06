<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se um admin desativar a conta de alguém que já está logado, essa
 * pessoa é derrubada da sessão no próximo clique, em vez de continuar
 * navegando até o token/sessão expirar sozinho.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Seu acesso foi desativado. Fale com o administrador do sistema.']);
        }

        return $next($request);
    }
}
