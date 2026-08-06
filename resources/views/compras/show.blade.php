@extends('layouts.app')

@section('title', 'Compra ' . $compra->uts)

@section('content')
    <div class="card">
        <div class="card__header"><h2>Dados da compra</h2></div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field"><label>UTS</label><div>{{ $compra->uts }}</div></div>
                <div class="field"><label>Mês/Ano</label><div>{{ $compra->mes_ano->format('m/Y') }}</div></div>
                <div class="field"><label>Fornecedor</label><div>{{ $compra->fornecedor->nome }}</div></div>
                <div class="field"><label>CNPJ</label><div>{{ $compra->fornecedor->cnpj }}</div></div>
                <div class="field"><label>Armazém</label><div>{{ \App\Models\Compra::armazens()[$compra->armazem] }}</div></div>
                <div class="field"><label>Certificação</label><div>{{ \App\Models\Compra::certificacoes()[$compra->certificacao] }}</div></div>
                <div class="field"><label>Tipo de entrada</label><div>{{ $compra->tipo_entrada }}</div></div>
                <div class="field"><label>Volume entregue</label><div>{{ number_format($compra->volume_sacas, 2, ',', '.') }} sacas</div></div>
            </div>
            <p style="color:var(--muted); font-size:12.5px; margin-top:14px; margin-bottom:0;">
                Lançada por {{ $compra->criadoPor->name }} em {{ $compra->created_at->format('d/m/Y H:i') }}.
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <h2>Seleção e classificação</h2>
            <a href="{{ route('compras.classificacao.edit', $compra) }}" class="btn btn-ghost" style="padding:6px 12px; font-size:13px;">
                {{ $compra->classificacao ? 'Editar' : 'Classificar' }}
            </a>
        </div>
        <div class="card__body">
            @if ($compra->classificacao)
                @php($c = $compra->classificacao)
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>Padrão final</th><th class="num">SCS 17/18</th><th class="num">SCS 14/16</th><th class="num">Mercado interno</th><th class="num">Grinders</th><th class="num">Qtd. lotes</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ $c->padrao_final === 'FINE_CUP' ? 'Fine Cup' : 'Good Cup' }}</td>
                                <td class="num">{{ number_format($c->peneira_1718_sacas, 2, ',', '.') }} ({{ number_format($c->peneira_1718_pct, 1, ',', '.') }}%)</td>
                                <td class="num">{{ number_format($c->peneira_1416_sacas, 2, ',', '.') }} ({{ number_format($c->peneira_1416_pct, 1, ',', '.') }}%)</td>
                                <td class="num">{{ number_format($c->mercado_interno_sacas, 2, ',', '.') }} ({{ number_format($c->mercado_interno_pct, 1, ',', '.') }}%)</td>
                                <td class="num">{{ number_format($c->grinders_sacas, 2, ',', '.') }} ({{ number_format($c->grinders_pct, 1, ',', '.') }}%)</td>
                                <td class="num">{{ number_format($c->quantidade_lotes, 4, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @else
                <p style="color:var(--muted); margin:0;">Esta compra ainda não foi classificada.</p>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <h2>Financeiro</h2>
            <a href="{{ route('compras.financeiro.edit', $compra) }}" class="btn btn-ghost" style="padding:6px 12px; font-size:13px;">
                {{ $compra->financeiro ? 'Editar' : 'Lançar' }}
            </a>
        </div>
        <div class="card__body">
            @if ($compra->financeiro)
                @php($f = $compra->financeiro)
                <div class="form-grid">
                    <div class="field"><label>Valor da saca</label><div>R$ {{ number_format($f->valor_saca, 2, ',', '.') }}</div></div>
                    <div class="field"><label>Valor total</label><div>R$ {{ number_format($f->valor_total, 2, ',', '.') }}</div></div>
                    <div class="field"><label>Corretor</label><div>{{ $f->corretor_nome ?? '—' }}</div></div>
                    <div class="field"><label>Comissão</label><div>{{ $f->comissao_pct ? number_format($f->comissao_pct, 2, ',', '.') . '%' : '—' }}</div></div>
                </div>
            @else
                <p style="color:var(--muted); margin:0;">Nenhum dado financeiro lançado ainda.</p>
            @endif
        </div>
    </div>
@endsection
