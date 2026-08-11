{{-- Estoque agrupado por armazém (tela interna). Espera $linhas (com a
     coluna `armazem`) e $totalGeral. A versão pública usa a partial
     _tabela-classificacao, que não quebra por armazém. --}}
<div class="table-wrap" style="border:0; border-radius:0;">
    <table class="data">
        <thead>
            <tr>
                <th>Armazém</th>
                <th>Padrão</th>
                <th class="num">SCS 17/18</th>
                <th class="num">SCS 14/16</th>
                <th class="num">Mercado interno</th>
                <th class="num">Grinders</th>
                <th class="num">Moka</th>
                <th class="num">Total de SCS</th>
                <th class="num">% do total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas->groupBy('armazem') as $armazem => $doArmazem)
                @foreach ($doArmazem as $i => $linha)
                    <tr>
                        {{-- O nome do armazém aparece só na primeira linha do grupo. --}}
                        <td>{{ $i === 0 ? (\App\Models\Compra::armazens()[$armazem] ?? $armazem) : '' }}</td>
                        <td>{{ \App\Models\Classificacao::padroes()[$linha->padrao_final] ?? $linha->padrao_final }}</td>
                        <td class="num">{{ number_format($linha->scs_1718, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($linha->scs_1416, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($linha->mercado_interno, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($linha->grinders, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($linha->moka, 2, ',', '.') }}</td>
                        <td class="num"><strong>{{ number_format($linha->total, 2, ',', '.') }}</strong></td>
                        <td class="num">{{ $totalGeral > 0 ? number_format(($linha->total / $totalGeral) * 100, 1, ',', '.') : '0,0' }}%</td>
                    </tr>
                @endforeach

                @if ($doArmazem->count() > 1)
                    <tr class="linha-subtotal">
                        <td></td>
                        <td>Subtotal {{ \App\Models\Compra::armazens()[$armazem] ?? $armazem }}</td>
                        <td class="num">{{ number_format($doArmazem->sum('scs_1718'), 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($doArmazem->sum('scs_1416'), 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($doArmazem->sum('mercado_interno'), 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($doArmazem->sum('grinders'), 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($doArmazem->sum('moka'), 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($doArmazem->sum('total'), 2, ',', '.') }}</td>
                        <td class="num">{{ $totalGeral > 0 ? number_format(($doArmazem->sum('total') / $totalGeral) * 100, 1, ',', '.') : '0,0' }}%</td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="9" style="text-align:center; color:var(--muted); padding:24px;">
                        Nenhuma compra classificada em estoque neste recorte.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($linhas->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="2">Total geral</td>
                    <td class="num">{{ number_format($linhas->sum('scs_1718'), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($linhas->sum('scs_1416'), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($linhas->sum('mercado_interno'), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($linhas->sum('grinders'), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($linhas->sum('moka'), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($totalGeral, 2, ',', '.') }}</td>
                    <td class="num">100,0%</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
