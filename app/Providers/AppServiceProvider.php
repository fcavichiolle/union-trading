<?php

namespace App\Providers;

use App\Services\PainelInicial;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton: o layout (badges do menu) e a home pedem os mesmos
        // números na mesma requisição — assim as queries rodam uma vez só.
        $this->app->singleton(PainelInicial::class);
    }

    public function boot(): void
    {
        // Em produção, força geração de URLs com https:// (evita links
        // http:// gerados por engano atrás de um proxy/load balancer),
        // e cookies de sessão marcados "secure" (ver config/session.php
        // + SESSION_SECURE_COOKIE no .env).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Paginador próprio para toda lista com `->links()`. O padrão do
        // Laravel é feito para Tailwind; sem aquele CSS as setas em SVG
        // ocupavam a tela inteira assim que uma lista passava de uma página.
        Paginator::defaultView('partials.paginacao');

        // Badges de pendência no menu lateral, em todas as telas autenticadas.
        View::composer('layouts.app', function ($view) {
            $user = Auth::user();

            $view->with('badgesMenu', $user
                ? $this->app->make(PainelInicial::class)->badgesMenu($user)
                : []);
        });
    }
}
