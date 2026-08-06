<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Página inicial pós-login. Mostra só os atalhos que fazem sentido
 * para o perfil do usuário (o menu por setor é montado aqui e
 * também no layout principal), mas a proteção de verdade contra
 * acesso indevido está nas rotas (middleware 'role'), não na
 * exibição do menu.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard.home', [
            'user' => Auth::user(),
        ]);
    }
}
