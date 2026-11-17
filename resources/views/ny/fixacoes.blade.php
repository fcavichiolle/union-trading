@extends('layouts.app')

@section('title', 'Fixações registradas')
@section('subtitle', 'Histórico das tranches fixadas na Tela NY. Filtre por corretora ou contrato para achar uma fixação antiga, baixe o recorte em PDF, reabra a instrução de AA ou a carta de fixação do contrato.')

@section('crumb')
    <span>Mercado</span><span class="sep">/</span><b>Fixações registradas</b>
@endsection

@section('content')
    <div class="card">
        <div class="card__header"><h2>Fixações registradas</h2></div>
        <div class="card__body" style="padding:0;">
            <div style="padding:16px 20px 0;">
                {{-- Filtro por corretora e por contrato. A corretora vem
                     das próprias fixações (snapshot), não do cadastro:
                     assim não aparece opção sem nenhum registro, e o
                     histórico de um cadastro renomeado continua achável. --}}
                <form method="GET" action="{{ route('ny.fixacoes.index') }}" class="filter-bar">
                    <div class="field">
                        <label for="f_corretora">Corretora</label>
                        <select id="f_corretora" name="corretora">
                            <option value="">Todas</option>
                            @foreach ($corretorasFixadas as $nome)
                                <option value="{{ $nome }}" @selected($filtros['corretora'] === $nome)>{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="f_contrato">Contrato</label>
                        <select id="f_contrato" name="contrato">
                            <option value="">Todos</option>
                            @foreach ($contratosFixados as $c)
                                <option value="{{ $c->id }}" @selected($filtros['contrato'] === (string) $c->id)>
                                    UT {{ $c->numero_ut }} — {{ $c->cliente_nome }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    @unless ($semFiltro)
                        <a href="{{ route('ny.fixacoes.index') }}" class="btn btn-ghost">Limpar</a>
                    @endunless
                    <a href="{{ route('ny.fixacoes.pdf', array_filter($filtros)) }}" class="btn btn-ghost">↓ PDF</a>
                </form>

                <p style="margin:0 0 14px; font-size:12.5px; color:var(--muted);">
                    @if ($semFiltro)
                        Últimas {{ $ultimas }} fixações — filtre por corretora ou contrato para ver o histórico completo.
                    @else
                        {{ $fixacoes->count() === 1 ? '1 fixação' : $fixacoes->count() . ' fixações' }} neste filtro. O PDF sai com este mesmo recorte.
                    @endif
                </p>
            </div>

            <div class="table-wrap" style="border:0; border-radius:0;">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Contrato</th>
                            <th>Corretora</th>
                            <th>Tela</th>
                            <th class="num">Lotes</th>
                            <th class="num">Level</th>
                            <th class="num">Dif.</th>
                            <th class="num">Preço</th>
                            <th>Por</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($fixacoes as $f)
                            <tr>
                                <td>{{ $f->created_at->format('d/m/Y H:i') }}</td>
                                <td><strong>UT {{ $f->contrato?->numero_ut }}</strong></td>
                                <td>
                                    {{ $f->corretora }}
                                    {{-- "cliente" aqui é o COMPRADOR do contrato, não o
                                         broker dele: numa lista de fixações o que se
                                         procura é com quem é o negócio. O broker do
                                         cliente continua na tela do contrato e no PDF. --}}
                                    @if ($f->contrato?->cliente_nome)
                                        <br><span style="color:var(--muted); font-size:11.5px;">cliente: {{ $f->contrato->cliente_nome }}</span>
                                    @endif
                                </td>
                                <td>{{ $f->tela ?: '—' }}</td>
                                <td class="num">{{ $f->lotes }}</td>
                                <td class="num">{{ number_format((float) $f->level, 2, ',', '.') }}</td>
                                <td class="num">{{ number_format((float) $f->diferencial, 2, ',', '.') }}</td>
                                <td class="num"><strong>{{ number_format((float) $f->preco, 2, ',', '.') }}</strong> {{ $f->contrato?->unidadePreco() }}</td>
                                <td>{{ $f->criadoPor?->name ?? '—' }}</td>
                                <td class="cell-action">
                                    @php $aa = $instrucoesAA[$f->id] ?? null; @endphp
                                    <div style="display:flex; gap:6px; justify-content:flex-end; align-items:center;">
                                        {{-- Abre o Outlook com a instrução de AA deste negócio (todos os
                                             contratos do mesmo cliente fixados junto com esta tranche). --}}
                                        @if ($aa?->enviavel())
                                            <a href="{{ $aa->mailto() }}" class="btn btn-ghost"
                                               style="padding:6px 12px; font-size:13px; white-space:nowrap;"
                                               title="Abrir o Outlook com a instrução de AA para {{ $aa->cliente }} ({{ $aa->corretora }})">✉ AA</a>
                                        @endif
                                        {{-- Carta de fixação do CONTRATO desta tranche: traz
                                             todas as fixações dele, não só esta linha. --}}
                                        @if ($f->contrato)
                                            <a href="{{ route('contratos.carta-fixacao', $f->contrato) }}" class="btn btn-ghost"
                                               style="padding:6px 12px; font-size:13px; white-space:nowrap;"
                                               title="Baixar a carta de fixação do contrato UT {{ $f->contrato->numero_ut }}">↓ Carta</a>
                                        @endif
                                        <form method="POST" action="{{ route('ny.fixacoes.destroy', $f) }}"
                                              onsubmit="return confirm('Excluir esta fixação? O saldo do contrato será recalculado (um contrato FIXED pode voltar a A FIXAR).');"
                                              style="margin:0;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost" style="padding:6px 12px; font-size:13px; color:var(--danger-text);">Excluir</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" style="text-align:center; color:var(--muted); padding:24px;">
                                {{-- Com filtro ativo, "nenhuma registrada ainda"
                                     seria mentira: existem, só não neste recorte. --}}
                                {{ $semFiltro ? 'Nenhuma fixação registrada ainda.' : 'Nenhuma fixação neste filtro.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
