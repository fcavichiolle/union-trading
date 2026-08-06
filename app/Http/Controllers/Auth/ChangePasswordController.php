<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Tela de "Alterar senha" para usuário já autenticado — usada tanto
 * espontaneamente quanto quando force_password_change = true
 * (senha temporária criada pelo admin).
 */
class ChangePasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.change-password', [
            'obrigatorio' => (bool) Auth::user()->force_password_change,
        ]);
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($request->string('password')),
            'force_password_change' => false,
        ])->save();

        AuditLog::registrar('senha_alterada', null, $user->id);

        return redirect()->route('dashboard')->with('status', 'Senha alterada com sucesso.');
    }
}
