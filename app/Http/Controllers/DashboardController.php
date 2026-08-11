<?php

namespace App\Http\Controllers;

use App\Services\PainelInicial;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Página inicial pós-login: um painel do que falta fazer (pendências),
 * a posição geral e os últimos lançamentos. O que cada perfil enxerga é
 * decidido no PainelInicial, mas a proteção de verdade contra acesso
 * indevido está nas rotas (middleware 'role'), não na exibição.
 */
class DashboardController extends Controller
{
    public function index(PainelInicial $painel): View
    {
        $user = Auth::user();

        return view('dashboard.home', [
            'user' => $user,
            'pendencias' => $painel->pendencias($user),
            'numeros' => $painel->numeros(),
            'ultimasCompras' => $user->hasRole('admin', 'compras')
                ? $painel->ultimasCompras()
                : collect(),
            'ultimosContratos' => $user->hasRole('admin', 'compras')
                ? $painel->ultimosContratos()
                : collect(),
        ]);
    }
}
