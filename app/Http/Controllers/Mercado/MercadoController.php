<?php

namespace App\Http\Controllers\Mercado;

use App\Http\Controllers\Controller;
use App\Services\MercadoCafe;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class MercadoController extends Controller
{
    /** Página de cotações (café NY/Londres + câmbio). */
    public function index(MercadoCafe $mercado): View
    {
        // O primeiro paint já sai com dados do servidor; o JS da página
        // refaz o fetch em /api/market a cada 30s.
        return view('mercado.index', ['snapshot' => $mercado->snapshot()]);
    }

    /** JSON consumido pela página de cotações e pela Tela NY (cache 30s no serviço). */
    public function api(MercadoCafe $mercado): JsonResponse
    {
        return response()->json($mercado->snapshot());
    }
}
