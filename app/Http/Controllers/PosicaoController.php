<?php

namespace App\Http\Controllers;

use App\Models\FotoEstoque;
use App\Services\LongShort;
use Illuminate\Contracts\View\View;

/**
 * Posição Long & Short: quanto café há (e vai haver) de cada padrão contra
 * o que está vendido, mês a mês. Só leitura — os números vêm das compras,
 * dos embarques e da foto do estoque. A conta está em `LongShort`.
 */
class PosicaoController extends Controller
{
    public function index(LongShort $posicao): View
    {
        return view('posicao.index', [
            'p' => $posicao->calcular(),
            'baldes' => FotoEstoque::baldes(),
        ]);
    }
}
