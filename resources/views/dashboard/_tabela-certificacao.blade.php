{{-- Espera as variáveis: $linhasCertificacao (Collection) e $totalGeral (float) --}}
<div class="table-wrap" style="border:0; border-radius:0;">
    <table class="data">
        <thead>
            <tr>
                <th>Certificação</th>
                <th class="num">SCS 17/18</th>
                <th class="num">SCS 14/16</th>
                <th class="num">Mercado interno</th>
                <th class="num">Grinders</th>
                <th class="num">Total de SCS</th>
                <th class="num">% do total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhasCertificacao as $linha)
                <tr>
                    <td>{{ \App\Models\Compra::certificacoes()[$linha->certificacao] ?? $linha->certificacao }}</td>
                    <td class="num">{{ number_format($linha->scs_1718, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($linha->scs_1416, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($linha->mercado_interno, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($linha->grinders, 2, ',', '.') }}</td>
                    <td class="num"><strong>{{ number_format($linha->total, 2, ',', '.') }}</strong></td>
                    <td class="num">{{ $totalGeral > 0 ? number_format(($linha->total / $totalGeral) * 100, 1, ',', '.') : '0,0' }}%</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center; color:var(--muted); padding:24px;">Nenhuma compra classificada neste período.</td></tr>
            @endforelse
        </tbody>
        @if ($linhasCertificacao->isNotEmpty())
            <tfoot>
                <tr>
                    <td>Total geral</td>
                    <td class="num">{{ number_format($linhasCertificacao->sum('scs_1718'), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($linhasCertificacao->sum('scs_1416'), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($linhasCertificacao->sum('mercado_interno'), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($linhasCertificacao->sum('grinders'), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($totalGeral, 2, ',', '.') }}</td>
                    <td class="num">100,0%</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>