@extends('layouts.app')

@section('title', 'Compra ' . $compra->uts)
@section('subtitle', $compra->fornecedor->nome . ' · ' . $compra->data_compra?->format('d/m/Y'))

@section('crumb')
    <span>Compras &amp; Classificação</span><span class="sep">/</span>
    <a href="{{ route('compras.index') }}" style="color:inherit;">Compras lançadas</a><span class="sep">/</span>
    <b>{{ $compra->uts }}</b>
@endsection

@section('page_actions')
    <a href="{{ route('compras.edit', $compra) }}" class="btn btn-ghost">Editar compra</a>
@endsection

@section('content')
    @php
        $entregues = $compra->sacasEntregues();
        $saldo = $compra->saldoAEntregar();
        $semLote = $compra->entregas->filter(fn ($e) => $e->precisaDeNumeroLote());
        $divergente = $compra->divergenciaPendente();
    @endphp

    {{-- Situação da entrega em uma linha: é a pergunta do funcionário 3. --}}
    <div class="calc-grid" style="margin-bottom:20px;">
        <div class="calc-item">
            <span class="calc-lbl">Contratado</span>
            <span class="calc-val">{{ number_format((float) $compra->volume_contratado, 2, ',', '.') }} sc</span>
        </div>
        <div class="calc-item">
            <span class="calc-lbl">Entregue</span>
            <span class="calc-val">{{ number_format($entregues, 2, ',', '.') }} sc</span>
        </div>
        <div class="calc-item">
            <span class="calc-lbl">
                @if ($compra->liquidada())
                    Liquidada
                @else
                    {{ $saldo < 0 ? 'Entregue a mais' : 'Falta entregar' }}
                @endif
            </span>
            <span class="calc-val {{ $divergente ? 'is-alerta' : '' }}">
                @if ($compra->liquidada())
                    {{ number_format($entregues, 2, ',', '.') }} sc
                @else
                    {{ number_format(abs($saldo), 2, ',', '.') }} sc
                @endif
            </span>
        </div>
        <div class="calc-item">
            <span class="calc-lbl">Valor efetivo</span>
            <span class="calc-val">
                {{ $compra->valorEntregue() === null ? '—' : 'R$ ' . number_format($compra->valorEntregue(), 2, ',', '.') }}
            </span>
        </div>
    </div>

    @error('liquidacao')
        <div class="alert alert-error">{{ $message }}</div>
    @enderror

    {{-- Liquidação: enquanto ninguém decide, a diferença pode ser café a
         receber de verdade — por isso o aviso fica. Liquidar encerra a
         compra com o que entrou. --}}
    @if ($compra->liquidada())
        <div class="alert alert-success" style="display:flex; align-items:baseline; gap:10px; flex-wrap:wrap;">
            <strong>Compra liquidada com {{ number_format($entregues, 2, ',', '.') }} sc.</strong>
            <span>
                Encerrada em {{ $compra->liquidada_em->format('d/m/Y \à\s H:i') }}
                @if ($compra->liquidadaPor) por {{ $compra->liquidadaPor->name }} @endif —
                o sistema reconhece este volume como final
                @if (abs($saldo) > 0.01)
                    (contratado era {{ number_format((float) $compra->volume_contratado, 2, ',', '.') }} sc)
                @endif.
            </span>
            <form method="POST" action="{{ route('compras.reabrir', $compra) }}" style="margin-left:auto;"
                  onsubmit="return confirm('Reabrir a UTS {{ $compra->uts }}? A diferença entre contratado e entregue volta a aparecer como pendência.');">
                @csrf @method('PATCH')
                <button type="submit" class="mini">Reabrir</button>
            </form>
        </div>
    @elseif ($divergente)
        <div class="alert" style="background:#FCF3DC; color:#8A6116; border:1px solid #EBD9A8; display:flex; align-items:baseline; gap:10px; flex-wrap:wrap;">
            <strong>
                @if ($saldo > 0)
                    Faltam {{ number_format($saldo, 2, ',', '.') }} sc para completar o contratado.
                @else
                    Entraram {{ number_format(abs($saldo), 2, ',', '.') }} sc a mais que o contratado.
                @endif
            </strong>
            <span>
                Se não vem (nem sai) mais nada, <strong>liquide a compra</strong>: o sistema passa a reconhecer
                as {{ number_format($entregues, 2, ',', '.') }} sc entregues como o volume final e este aviso desaparece.
            </span>
            <form method="POST" action="{{ route('compras.liquidar', $compra) }}" style="margin-left:auto;"
                  onsubmit="return confirm('Liquidar a UTS {{ $compra->uts }} com {{ number_format($entregues, 2, ',', '.') }} sc? O contratado fica registrado como histórico.');">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-primary" style="padding:6px 14px; font-size:13px;">Liquidar compra</button>
            </form>
        </div>
    @endif

    @if ($semLote->isNotEmpty())
        <div class="alert alert-error">
            <strong>{{ $semLote->count() }} {{ \Illuminate\Support\Str::plural('entrega', $semLote->count()) }} sem número de lote.</strong>
            <span class="alert__hint">
                Enquanto o armazém não informar o lote, esse café não conta como estoque definitivo.
            </span>
        </div>
    @endif

    <div class="card">
        <div class="card__header"><h2>Dados da compra</h2></div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field"><label>UTS</label><div>{{ $compra->uts }}</div></div>
                <div class="field"><label>Data da compra</label><div>{{ $compra->data_compra?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="field"><label>Vendedor</label><div>{{ $compra->fornecedor->nome }}</div></div>
                <div class="field">
                    <label>CNPJ / CPF</label>
                    <div>
                        {{ $compra->fornecedor->documentoFormatado() ?? '' }}
                        @unless ($compra->fornecedor->documento)
                            <span class="badge badge--amber">a confirmar</span>
                        @endunless
                    </div>
                </div>
                <div class="field"><label>Certificação</label><div>{{ \App\Models\Compra::certificacoes()[$compra->certificacao] ?? $compra->certificacao }}</div></div>
                <div class="field"><label>Logística</label><div>{{ $compra->logisticaLabel() ?? '—' }}</div></div>
                <div class="field"><label>Tipo de entrada</label><div>{{ $compra->tipo_entrada }}</div></div>
                <div class="field"><label>Volume contratado</label><div>{{ number_format((float) $compra->volume_contratado, 2, ',', '.') }} sacas</div></div>
            </div>
            <p style="color:var(--muted); font-size:12.5px; margin-top:14px; margin-bottom:0;">
                Lançada por {{ $compra->criadoPor?->name ?? '—' }} em {{ $compra->created_at->format('d/m/Y H:i') }}.
            </p>
        </div>
    </div>

    {{-- Entregas: a tela do funcionário 3 --}}
    <div class="card">
        <div class="card__header"><h2>Entregas no armazém</h2></div>
        <div class="card__body" style="padding:0;">
            <div class="table-wrap" style="border:0; border-radius:0;">
                <table class="data tabela-entregas">
                    <thead>
                        <tr>
                            <th>Mês/Ano</th>
                            <th>Armazém</th>
                            <th class="num">Sacas</th>
                            <th class="num">Valor da entrega</th>
                            <th>Nº do lote</th>
                            <th>Lançada por</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($compra->entregas->sortBy('mes_ano') as $entrega)
                            @php
                                $valorEntrega = $compra->valor_saca === null
                                    ? null
                                    : (float) $entrega->volume_sacas * (float) $compra->valor_saca;
                            @endphp
                            <tr>
                                <form method="POST" action="{{ route('compras.entregas.update', [$compra, $entrega]) }}" id="e-{{ $entrega->id }}">
                                    @csrf @method('PUT')
                                </form>
                                <td><input type="month" form="e-{{ $entrega->id }}" name="mes_ano" value="{{ $entrega->mes_ano->format('Y-m') }}" required></td>
                                <td>
                                    <select form="e-{{ $entrega->id }}" name="armazem" required>
                                        @foreach (\App\Models\Compra::armazens() as $cod => $rotulo)
                                            <option value="{{ $cod }}" @selected($entrega->armazem === $cod)>{{ $rotulo }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="num">
                                    <input type="number" step="0.01" min="0.01" form="e-{{ $entrega->id }}" name="volume_sacas"
                                           value="{{ rtrim(rtrim((string) $entrega->volume_sacas, '0'), '.') }}" required
                                           class="campo-sacas">
                                </td>
                                <td class="num" style="font-family:var(--font-data); font-size:13px;">
                                    {{ $valorEntrega === null ? '—' : 'R$ ' . number_format($valorEntrega, 2, ',', '.') }}
                                </td>
                                <td>
                                    <input type="text" form="e-{{ $entrega->id }}" name="numero_lote" value="{{ $entrega->numero_lote }}"
                                           placeholder="Ex.: L-2026-0451" class="campo-lote">
                                </td>
                                <td style="font-size:12.5px; color:var(--muted);">
                                    {{ $entrega->criadoPor?->name ?? '—' }}<br>{{ $entrega->created_at->format('d/m/Y') }}
                                </td>
                                <td class="cell-action" style="display:flex; gap:6px; justify-content:flex-end;">
                                    <button type="submit" form="e-{{ $entrega->id }}" class="mini">Salvar</button>
                                    <form method="POST" action="{{ route('compras.entregas.destroy', [$compra, $entrega]) }}"
                                          onsubmit="return confirm('Remover esta entrega? O saldo da UTS será recalculado.');" style="margin:0;">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="mini mini--danger">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align:center; color:var(--muted); padding:24px;">
                                    Nenhuma entrega lançada — o café desta UTS ainda não entrou no armazém.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($compra->entregas->count() > 1)
                        <tfoot>
                            <tr>
                                <td colspan="2">Total entregue</td>
                                <td class="num"><strong>{{ number_format($entregues, 2, ',', '.') }}</strong></td>
                                <td class="num" style="font-family:var(--font-data); font-size:13px;">
                                    <strong>{{ $compra->valorEntregue() === null ? '—' : 'R$ ' . number_format($compra->valorEntregue(), 2, ',', '.') }}</strong>
                                </td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            {{-- Nova entrega --}}
            <div style="padding:16px 20px; box-shadow: inset 0 1px 0 var(--border);">
                <form method="POST" action="{{ route('compras.entregas.store', $compra) }}"
                      style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                    @csrf
                    <div class="field {{ $errors->has('mes_ano') ? 'has-error' : '' }}" style="margin-bottom:0;">
                        <label for="mes_ano">Mês/ano da entrega</label>
                        <input type="month" id="mes_ano" name="mes_ano" value="{{ old('mes_ano', date('Y-m')) }}" required>
                        @error('mes_ano') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field {{ $errors->has('armazem') ? 'has-error' : '' }}" style="margin-bottom:0;">
                        <label for="armazem">Armazém</label>
                        <select id="armazem" name="armazem" required>
                            @foreach (\App\Models\Compra::armazens() as $cod => $rotulo)
                                <option value="{{ $cod }}" @selected(old('armazem') === $cod)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        @error('armazem') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field {{ $errors->has('volume_sacas') ? 'has-error' : '' }}" style="margin-bottom:0;">
                        <label for="volume_sacas">Sacas que entraram</label>
                        <input type="number" step="0.01" min="0.01" id="volume_sacas" name="volume_sacas"
                               value="{{ old('volume_sacas', $saldo > 0 ? rtrim(rtrim(number_format($saldo, 2, '.', ''), '0'), '.') : '') }}"
                               style="width:140px;" required>
                        @error('volume_sacas') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field" style="margin-bottom:0;">
                        <label for="numero_lote">Nº do lote <span class="hint">(quando o armazém informar)</span></label>
                        <input type="text" id="numero_lote" name="numero_lote" value="{{ old('numero_lote') }}" placeholder="Ex.: L-2026-0451">
                    </div>
                    <button type="submit" class="btn btn-primary">Lançar entrega</button>
                </form>
                <p style="margin:12px 0 0; font-size:12.5px; color:var(--muted);">
                    Lance o que <strong>realmente entrou</strong>: o volume pode ficar acima ou abaixo do contratado,
                    e é ele que vale para o estoque e para o pagamento.
                </p>
            </div>
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
                            <tr><th>Padrão final</th><th>Tipo de bebida</th><th class="num">SCS 17/18</th><th class="num">SCS 14/16</th><th class="num">Mercado interno</th><th class="num">Grinders</th><th class="num">Moka</th><th class="num">Qtd. lotes</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ \App\Models\Classificacao::padroes()[$c->padrao_final] ?? $c->padrao_final }}</td>
                                <td>{{ $c->tipoBebidaLabel() ?? '—' }}</td>
                                <td class="num">{{ number_format($c->peneira_1718_sacas, 2, ',', '.') }} ({{ number_format($c->peneira_1718_pct, 1, ',', '.') }}%)</td>
                                <td class="num">{{ number_format($c->peneira_1416_sacas, 2, ',', '.') }} ({{ number_format($c->peneira_1416_pct, 1, ',', '.') }}%)</td>
                                <td class="num">{{ number_format($c->mercado_interno_sacas, 2, ',', '.') }} ({{ number_format($c->mercado_interno_pct, 1, ',', '.') }}%)</td>
                                <td class="num">{{ number_format($c->grinders_sacas, 2, ',', '.') }} ({{ number_format($c->grinders_pct, 1, ',', '.') }}%)</td>
                                <td class="num">{{ number_format($c->moka_sacas, 2, ',', '.') }} ({{ number_format($c->moka_pct, 1, ',', '.') }}%)</td>
                                <td class="num">{{ number_format($c->quantidade_lotes, 4, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @if ($compra->entregas->pluck('armazem')->unique()->count() > 1)
                    <p style="color:var(--muted); font-size:12.5px; margin:12px 0 0;">
                        A classificação é da UTS inteira. Como o café entrou em mais de um armazém, o Estoque
                        distribui estas peneiras entre eles na proporção das sacas de cada entrega.
                    </p>
                @endif
            @else
                <p style="color:var(--muted); margin:0;">Esta compra ainda não foi classificada.</p>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <h2>Financeiro</h2>
            <a href="{{ route('compras.financeiro.edit', $compra) }}" class="btn btn-ghost" style="padding:6px 12px; font-size:13px;">
                {{ $compra->valor_saca !== null ? 'Editar' : 'Lançar' }}
            </a>
        </div>
        <div class="card__body">
            @if ($compra->valor_saca !== null)
                <div class="form-grid">
                    <div class="field"><label>Valor da saca</label><div>R$ {{ number_format((float) $compra->valor_saca, 2, ',', '.') }}</div></div>
                    <div class="field"><label>Valor contratado</label><div>R$ {{ number_format($compra->valorContratado(), 2, ',', '.') }}</div></div>
                    <div class="field"><label>Valor efetivo (entregue)</label><div>R$ {{ number_format($compra->valorEntregue(), 2, ',', '.') }}</div></div>
                    <div class="field"><label>Corretor</label><div>{{ $compra->corretor_nome ?? '—' }}</div></div>
                    <div class="field"><label>Comissão</label><div>{{ $compra->comissao_pct ? number_format((float) $compra->comissao_pct, 2, ',', '.') . '%' : '—' }}</div></div>
                    <div class="field"><label>Pagamento</label>
                        <div>
                            {{ $compra->pagamento_previsto?->format('d/m/Y') ?? '—' }}
                            @if ($compra->pagamento_obs)
                                <br><span style="color:var(--muted); font-size:12.5px;">{{ $compra->pagamento_obs }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <p style="color:var(--muted); margin:0;">Preço ainda não lançado.</p>
            @endif
        </div>
    </div>
@endsection
