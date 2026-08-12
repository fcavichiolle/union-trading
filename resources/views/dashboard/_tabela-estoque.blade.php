{{-- Estoque agrupado por armazém (tela interna). Espera $linhas (com a
     coluna `armazem`) e $totalGeral. A versão pública usa a partial
     _tabela-classificacao, que não quebra por armazém.

     As colunas de peneira vêm de Classificacao::faixas(), iguais ao SQL
     que as somou. --}}
@php($faixas = \App\Models\Classificacao::faixas())
<div class="table-wrap" style="border:0; border-radius:0;">
    <table class="data">
        <thead>
            <tr>
                <th>Armazém</th>
                <th>Padrão</th>
                @foreach ($faixas as $rotulo)
                    <th class="num">{{ $rotulo }}</th>
                @endforeach
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
                        <td>{{ \App\Models\Classificacao::padroes()[$linha->padrao_final] ?? ($linha->padrao_final ?? '—') }}</td>
                        @foreach (array_keys($faixas) as $faixa)
                            <td class="num">{{ number_format((float) $linha->{$faixa}, 2, ',', '.') }}</td>
                        @endforeach
                        <td class="num"><strong>{{ number_format($linha->total, 2, ',', '.') }}</strong></td>
                        <td class="num">{{ $totalGeral > 0 ? number_format(($linha->total / $totalGeral) * 100, 1, ',', '.') : '0,0' }}%</td>
                    </tr>
                @endforeach

                @if ($doArmazem->count() > 1)
                    <tr class="linha-subtotal">
                        <td></td>
                        <td>Subtotal {{ \App\Models\Compra::armazens()[$armazem] ?? $armazem }}</td>
                        @foreach (array_keys($faixas) as $faixa)
                            <td class="num">{{ number_format($doArmazem->sum($faixa), 2, ',', '.') }}</td>
                        @endforeach
                        <td class="num">{{ number_format($doArmazem->sum('total'), 2, ',', '.') }}</td>
                        <td class="num">{{ $totalGeral > 0 ? number_format(($doArmazem->sum('total') / $totalGeral) * 100, 1, ',', '.') : '0,0' }}%</td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="{{ count($faixas) + 4 }}" style="text-align:center; color:var(--muted); padding:24px;">
                        Nenhuma compra classificada em estoque neste recorte.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($linhas->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="2">Total geral</td>
                    @foreach (array_keys($faixas) as $faixa)
                        <td class="num">{{ number_format($linhas->sum($faixa), 2, ',', '.') }}</td>
                    @endforeach
                    <td class="num">{{ number_format($totalGeral, 2, ',', '.') }}</td>
                    <td class="num">100,0%</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
