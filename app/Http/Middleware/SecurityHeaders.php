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

        // CSP: a página só carrega e só conversa com o próprio servidor.
        // 'unsafe-inline' fica porque o sistema não tem etapa de build — o
        // JavaScript e o CSS moram dentro das views (e há `onsubmit` de
        // confirmação nos formulários). Ou seja: esta política NÃO barra
        // script injetado na própria página (quem barra é o escape do
        // Blade e o `textContent` no JS); ela barra o que viria depois —
        // carregar script de fora, mandar dado para outro servidor
        // (connect-src), trocar o destino de um formulário (form-action),
        // sequestrar links relativos (base-uri) e embutir a tela em outra
        // (frame-ancestors).
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));

        // A versão do PHP não é da conta de quem está de fora.
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        if ($request->secure()) {
            // HSTS: só faz sentido quando o site já está sendo servido em HTTPS.
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
