<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($credentials['email']) . '|' . $request->ip();

        // Camada extra de proteção contra força bruta além do
        // middleware throttle:login já aplicado na rota (defesa em
        // profundidade: mesmo se a rota mudar, o limite continua valendo).
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $segundos = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Muitas tentativas. Tente novamente em {$segundos} segundos.",
            ]);
        }

        // 'active' entra nas credenciais do Auth::attempt: uma conta
        // desativada recebe a MESMA mensagem genérica de erro que uma
        // senha errada, sem revelar se o e-mail existe ou está bloqueado.
        if (! Auth::attempt([...$credentials, 'active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            AuditLog::registrar('login_falho', "Tentativa de login para: {$credentials['email']}");

            throw ValidationException::withMessages([
                'email' => 'Credenciais inválidas.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Regenera o ID de sessão no login: evita session fixation
        // (impede que um ID de sessão obtido antes do login continue válido).
        $request->session()->regenerate();

        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();

        AuditLog::registrar('login_sucesso', null, $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuditLog::registrar('logout', null, Auth::id());

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
