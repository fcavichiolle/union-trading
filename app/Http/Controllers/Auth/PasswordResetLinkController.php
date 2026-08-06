<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Dispara o envio se o e-mail existir. O resultado ($status) NÃO
        // é usado para diferenciar a resposta ao usuário: sempre mostramos
        // a mesma mensagem genérica, para não revelar quais e-mails têm
        // conta no sistema (proteção contra enumeração de usuários).
        Password::sendResetLink($request->only('email'));

        AuditLog::registrar('solicitacao_reset_senha', $request->string('email'));

        return back()->with('status', 'Se este e-mail estiver cadastrado, enviamos um link de redefinição de senha.');
    }
}
