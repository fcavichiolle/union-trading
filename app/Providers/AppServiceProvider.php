<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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
    }
}
