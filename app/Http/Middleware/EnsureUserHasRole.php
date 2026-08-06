<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controla o acesso por perfil/setor (Módulo 0).
 *
 * Uso na rota: ->middleware('role:admin,compras')
 * Só usuários com role.slug em ('admin','compras') passam. Qualquer
 * outro perfil recebe 403 — nunca um redirecionamento silencioso que
 * possa confundir com "página não encontrada" vs "sem permissão", pois
 * ambos são tratados de forma explícita e seguros (não vazamos se o
 * recurso existe, apenas negamos acesso).
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(...$roles)) {
            AuditLog::registrar(
                'acesso_negado',
                "Rota [{$request->path()}] exigia perfil: " . implode(',', $roles),
                $user?->id
            );

            abort(403, 'Você não tem permissão para acessar este módulo.');
        }

        return $next($request);
    }
}
