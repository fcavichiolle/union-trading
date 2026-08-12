{{-- Espera as variáveis: $linhas (Collection) e $totalGeral (float).
     As colunas de peneira saem de Classificacao::faixas() — o SQL do
     DashboardController usa o mesmo prefixo como apelido de cada soma,
     então faixa nova aparece aqui sem editar esta tabela. --}}
@php($faixas = \App\Models\Classificacao::faixas())
<div class="table-wrap" style="border:0; border-radius:0;">
    <table class="data">
        <thead>
            <tr>
                <th>Padrão</th>
                @foreach ($faixas as $rotulo)
                    <th class="num">{{ $rotulo }}</th>
                @endforeach
                <th class="num">Total de SCS</th>
                <th class="num">% do total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $linha)
                <tr>
                    <td>{{ \App\Models\Classificacao::padroes()[$linha->padrao_final] ?? ($linha->padrao_final ?? '—') }}</td>
                    @foreach (array_keys($faixas) as $faixa)
                        <td class="num">{{ number_format((float) $linha->{$faixa}, 2, ',', '.') }}</td>
                    @endforeach
                    <td class="num"><strong>{{ number_format($linha->total, 2, ',', '.') }}</strong></td>
                    <td class="num">{{ $totalGeral > 0 ? number_format(($linha->total / $totalGeral) * 100, 1, ',', '.') : '0,0' }}%</td>
                </tr>
            @empty
                <tr><td colspan="{{ count($faixas) + 3 }}" style="text-align:center; color:var(--muted); padding:24px;">Nenhuma compra classificada neste período.</td></tr>
            @endforelse
        </tbody>
        @if ($linhas->isNotEmpty())
            <tfoot>
                <tr>
                    <td>Total geral</td>
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
