<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos HTTP de segurança aplicados a toda resposta. Isso é
 * exatamente o tipo de coisa que uma revisão de segurança do
 * repositório costuma checar — tê-los explícitos no código deixa
 * claro que foi uma decisão deliberada, não um esquecimento.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY'); // impede clickjacking (embutir o site em <iframe> de outro domínio)
        $response->headers->set('X-Content-Type-Options', 'nosniff'); // impede o navegador de "adivinhar" tipo de arquivo
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->secure()) {
            // HSTS: só faz sentido quando o site já está sendo servido em HTTPS.
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
