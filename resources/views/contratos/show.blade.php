@extends('layouts.app')

@section('title', 'UT ' . (preg_replace('/\D+/', '', $contrato->numero_ut) ?: $contrato->numero_ut))
@section('subtitle', $contrato->cliente_nome . ' · ' . $contrato->data_contrato->format('d/m/Y'))

@section('crumb')
    <span>Contratos</span><span class="sep">/</span>
    <a href="{{ route('contratos.index') }}" style="color:inherit;">Gerados</a><span class="sep">/</span><b>UT {{ preg_replace('/\D+/', '', $contrato->numero_ut) }}</b>
@endsection

@section('page_actions')
    @unless ($contrato->cancelado())
        <a href="{{ route('contratos.edit', $contrato) }}" class="btn btn-ghost">Editar</a>
    @endunless
    <a href="{{ route('contratos.pdf', $contrato) }}" class="btn-coffee js-save-pdf" data-filename="{{ $contrato->nomeArquivoPdf() }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"></path></svg>
        <span>Baixar PDF</span>
    </a>
@endsection

@section('content')
    @if ($contrato->cancelado())
        <div class="alert alert-error" style="margin-bottom:20px;">
            <strong>Contrato cancelado em {{ $contrato->cancelado_em->format('d/m/Y \à\s H:i') }}
                @if ($contrato->canceladoPor) por {{ $contrato->canceladoPor->name }} @endif.</strong>
            <div style="margin-top:6px; white-space:pre-line;">Motivo: {{ $contrato->motivo_cancelamento }}</div>
            <span class="alert__hint">
                O registro e o PDF continuam disponíveis para consulta, mas o contrato saiu da posição:
                não aparece na Tela NY nem entra nos números do painel.
            </span>

            <details class="reativar">
                <summary>Foi cancelado por engano? Reativar contrato</summary>
                <form method="POST" action="{{ route('contratos.reativar', $contrato) }}"
                      onsubmit="return confirm('Reativar o contrato UT {{ $contrato->numero_ut }}? Ele volta para a posição e para a Tela NY.');">
                    @csrf
                    @method('PATCH')
                    <div class="field {{ $errors->has('motivo') ? 'has-error' : '' }}">
                        <label for="motivo_reativar">Motivo da reativação</label>
                        <textarea id="motivo_reativar" name="motivo" rows="2" required
                                  placeholder="Ex.: cancelamento lançado no contrato errado — o cancelado deveria ser o UT 5941.">{{ old('motivo') }}</textarea>
                        @error('motivo') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:10px;">Reativar contrato</button>
                </form>
            </details>
        </div>
    @endif

    <div class="calc-grid" style="margin-bottom:20px;">
        <div class="calc-item"><span class="calc-lbl">Sacas</span><span class="calc-val">{{ number_format((float) $contrato->sacas, 2, ',', '.') }}</span></div>
        <div class="calc-item"><span class="calc-lbl">Lotes</span><span class="calc-val">{{ $contrato->lotes }}</span></div>
        <div class="calc-item"><span class="calc-lbl">Containers</span><span class="calc-val">{{ $contrato->containers }}</span></div>
        <div class="calc-item"><span class="calc-lbl">Peso / container</span><span class="calc-val">{{ number_format((float) $contrato->kg_por_container / 1000, 2, ',', '.') }} t</span></div>
    </div>

    <div class="card">
        <div class="card__header"><h2>Dados do contrato</h2></div>
        <div class="card__body">
            <table class="data" style="border:0;">
                <tbody>
                    <tr><td style="width:220px; color:var(--muted);">BUYER</td><td style="white-space:pre-line;"><strong>{{ $contrato->cliente_nome }}</strong>{{ "\n" . $contrato->cliente_endereco }}</td></tr>
                    <tr><td style="color:var(--muted);">BUYER REF NR</td><td>{{ $contrato->buyer_ref ?: '—' }}</td></tr>
                    <tr><td style="color:var(--muted);">QUALITY</td><td>{{ $contrato->qualidade_descricao }} <span class="badge badge--muted">{{ $contrato->tipo_cafe === 'CONILON' ? 'Conilon' : 'Arábica' }}</span></td></tr>
                    <tr><td style="color:var(--muted);">CERTIFIED</td><td>{{ $contrato->certificadoLabel() }}</td></tr>
                    <tr><td style="color:var(--muted);">QUANTITY</td><td>{{ $contrato->quantidadeLinha() }}</td></tr>
                    <tr><td style="color:var(--muted);">PACKAGING</td><td>{{ $contrato->embalagem }}</td></tr>
                    <tr><td style="color:var(--muted);">STATUS</td><td>
                        @if ($contrato->cancelado())
                            <span class="badge badge--red">CANCELADO</span>
                        @elseif ($contrato->fixado)
                            <span class="badge badge--green">FIXED</span>
                        @elseif ($contrato->parcialmenteFixado())
                            <span class="badge badge--amber">PARCIAL {{ $contrato->lotesFixados() }}/{{ $contrato->lotes }}</span>
                        @else
                            <span class="badge badge--muted">A FIXAR</span>
                        @endif
                    </td></tr>
                    <tr><td style="color:var(--muted);">PRICE</td><td>{{ $contrato->precoLinha() }}</td></tr>
                    <tr><td style="color:var(--muted);">SHIPMENT</td><td>{{ $contrato->embarqueLinha() ?: '—' }}</td></tr>
                    <tr><td style="color:var(--muted);">INCOTERMS</td><td>{{ $contrato->incotermsLinha() }}</td></tr>
                    <tr><td style="color:var(--muted);">REMARKS</td><td style="white-space:pre-line;">{{ $contrato->remarks ?: '—' }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    @if ($contrato->fixacoes->isNotEmpty())
        <div class="card" style="margin-top:20px;">
            <div class="card__header"><h2>Fixações (Tela NY)</h2></div>
            <div class="card__body" style="padding:0;">
                <div class="table-wrap" style="border:0; border-radius:0;">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Corretora</th>
                                <th>Tela</th>
                                <th class="num">Lotes</th>
                                <th class="num">Level</th>
                                <th class="num">Diferencial</th>
                                <th class="num">Preço ({{ $contrato->unidadePreco() }})</th>
                                <th>Por</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($contrato->fixacoes as $f)
                                <tr>
                                    <td>{{ $f->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        {{ $f->corretora }}
                                        @if ($f->broker_cliente)
                                            <br><span style="color:var(--muted); font-size:11.5px;">cliente: {{ $f->broker_cliente }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $f->tela ?: '—' }}</td>
                                    <td class="num">{{ $f->lotes }}</td>
                                    <td class="num">{{ number_format((float) $f->level, 2, ',', '.') }}</td>
                                    <td class="num">{{ number_format((float) $f->diferencial, 2, ',', '.') }}</td>
                                    <td class="num"><strong>{{ number_format((float) $f->preco, 2, ',', '.') }}</strong></td>
                                    <td>{{ $f->criadoPor?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($contrato->fixado)
                            <tfoot>
                                <tr>
                                    <td colspan="3">Preço final (média ponderada)</td>
                                    <td class="num">{{ $contrato->lotesFixados() }}</td>
                                    <td class="num" colspan="2"></td>
                                    <td class="num"><strong>{{ number_format((float) $contrato->preco_fixado, 2, ',', '.') }}</strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    @endif

    @unless ($contrato->cancelado())
        @php $qtdFixacoes = $contrato->fixacoes->count(); @endphp
        <details class="zona-risco">
            <summary>Cancelar ou excluir este contrato</summary>

            <div class="zona-risco__corpo">
                <form method="POST" action="{{ route('contratos.cancelar', $contrato) }}"
                      onsubmit="return confirm('Cancelar o contrato UT {{ $contrato->numero_ut }}? Ele sai da posição, mas o registro e o PDF continuam disponíveis.');">
                    @csrf
                    @method('PATCH')
                    <h3>Cancelar contrato</h3>
                    <p>
                        Para quando o cliente desiste do contrato. O registro <strong>fica no sistema</strong>
                        com o motivo, e o contrato sai da Tela NY e dos números do painel.
                        @if ($qtdFixacoes > 0)
                            As {{ $qtdFixacoes }} fixação(ões) já registradas continuam no histórico —
                            a operação de mercado aconteceu de verdade.
                        @endif
                    </p>
                    <div class="field {{ $errors->has('motivo') ? 'has-error' : '' }}">
                        <label for="motivo_cancelar">Motivo do cancelamento</label>
                        <textarea id="motivo_cancelar" name="motivo" rows="3" required
                                  placeholder="Ex.: cliente desistiu do embarque de setembro por queda de demanda.">{{ old('motivo') }}</textarea>
                        @error('motivo') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <button type="submit" class="btn btn-ghost" style="color:var(--danger-text);">Cancelar contrato</button>
                </form>

                <form method="POST" action="{{ route('contratos.destroy', $contrato) }}"
                      onsubmit="return confirm('EXCLUIR de vez o contrato UT {{ $contrato->numero_ut }}? Esta ação não pode ser desfeita.');">
                    @csrf
                    @method('DELETE')
                    <h3>Excluir contrato</h3>
                    <p>
                        Só para contrato <strong>lançado errado</strong>, que não deveria existir. Some do sistema
                        — o motivo fica registrado apenas no log de auditoria.
                        @if ($qtdFixacoes > 0)
                            <br><strong>Bloqueado:</strong> este contrato tem {{ $qtdFixacoes }} fixação(ões) na Tela NY.
                            Use "Cancelar contrato".
                        @endif
                    </p>
                    <div class="field">
                        <label for="motivo_excluir">Motivo da exclusão</label>
                        <textarea id="motivo_excluir" name="motivo" rows="3" required
                                  placeholder="Ex.: lançado em duplicidade — o contrato correto é o UT 5941."
                                  @disabled($qtdFixacoes > 0)></textarea>
                    </div>
                    <button type="submit" class="btn btn-ghost" style="color:var(--danger-text);" @disabled($qtdFixacoes > 0)>
                        Excluir definitivamente
                    </button>
                </form>
            </div>
        </details>
    @endunless
@endsection
