@extends('layouts.app')

@section('title', 'Controle logístico')
@section('subtitle', 'Agenda das estufagens, faturamento e baixa de estoque. A venda sai da posição quando o embarque recebe a baixa — não é preciso apagar nada.')

@section('crumb')
    <span>Exportação</span><span class="sep">/</span><b>Controle logístico</b>
@endsection

@php
    $n = fn ($v) => number_format((float) $v, 0, ',', '.');
    $d = fn ($data) => $data ? $data->format('d/m') : '—';
    $nomesMes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    $corStatus = [
        'ABERTO' => 'badge--muted', 'AG_BOOKING' => 'badge--amber', 'BOOKING' => 'badge--green',
        'AG_SI' => 'badge--amber', 'PROGRAMADO' => 'badge--green', 'FATURADO' => 'badge--muted', 'EMBARCADO' => 'badge--muted',
    ];
    $corPss = ['A_ENVIAR' => 'badge--red', 'ENVIADA' => 'badge--amber', 'APROVADA' => 'badge--green'];
@endphp

@section('content')
    {{-- Vendas em aberto por mês × standard: o lado "vendido" do Long & Short.
         Substitui o bloco de SUMIFS do fim da aba VENDAS-FOB. --}}
    <div class="card" style="margin-bottom:22px;">
        <div class="card__header card__header--dark mesh-texture">
            <h2>Vendas em aberto por mês × standard <span style="font-weight:400; opacity:.8;">(sacas)</span></h2>
        </div>
        <div class="card__body" style="padding:0;">
            <div class="table-wrap" style="border:0; border-radius:0;">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Standard</th>
                            @foreach ($posicao['colunas'] as $rotulo)
                                <th class="num">{{ $rotulo }}</th>
                            @endforeach
                            <th class="num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($posicao['linhas'] as $linha)
                            <tr>
                                <td><strong>{{ $linha['rotulo'] }}</strong></td>
                                @foreach ($linha['valores'] as $valor)
                                    <td class="num">
                                        @if ($valor > 0) {{ $n($valor) }} @else <span style="color:var(--muted);">—</span> @endif
                                    </td>
                                @endforeach
                                <td class="num"><strong>{{ $n($linha['total']) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            @foreach ($posicao['totais'] as $valor)
                                <td class="num"><strong>{{ $valor > 0 ? $n($valor) : '—' }}</strong></td>
                            @endforeach
                            <td class="num"><strong>{{ $n($posicao['total']) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <p style="margin:-10px 0 18px; font-size:12.5px; color:var(--muted);">
        Soma os embarques ainda sem baixa de estoque (no mês de faturamento de cada um) e o volume dos contratos
        que ainda não virou embarque (no mês de embarque do contrato). Faturado sem baixa continua contando:
        o café ainda está no armazém. Contrato cancelado não entra.
    </p>

    @foreach ($posicao['avisos'] as $aviso)
        <div class="alert alert-error" style="margin-bottom:14px;">⚠ {{ $aviso }}</div>
    @endforeach

    {{-- Fila de baixas: nota emitida, café ainda no estoque. Faturar e dar
         baixa são coisas diferentes — é a baixa que tira a venda da posição. --}}
    @if ($aguardandoBaixa->isNotEmpty())
        <div class="card" style="margin-bottom:22px;">
            <div class="card__header">
                <h2>Faturados aguardando baixa de estoque
                    <span style="font-weight:400; color:var(--muted); font-size:13px;">
                        — {{ $aguardandoBaixa->count() === 1 ? '1 embarque' : $aguardandoBaixa->count() . ' embarques' }},
                        {{ $n($aguardandoBaixa->sum('sacas')) }} sc ainda no estoque
                    </span>
                </h2>
            </div>
            <div class="card__body" style="padding:0;">
                <div class="table-wrap" style="border:0; border-radius:0;">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Ref.</th>
                                <th>Comprador</th>
                                <th>Standard</th>
                                <th>Faturado em</th>
                                <th>Navio</th>
                                <th class="num">Sacas</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($aguardandoBaixa as $e)
                                <tr>
                                    <td><a href="{{ route('contratos.show', $e->contrato) }}" style="white-space:nowrap;"><strong>{{ $e->referencia() }}</strong></a></td>
                                    <td>{{ \Illuminate\Support\Str::limit($e->contrato->cliente_nome, 30) }}</td>
                                    <td style="white-space:nowrap;">{{ $e->contrato->standardLabel() ?? '—' }}</td>
                                    <td style="white-space:nowrap;">{{ $e->faturado_em?->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ $e->navio ?? '—' }}</td>
                                    <td class="num"><strong>{{ $n($e->sacas) }}</strong></td>
                                    <td class="cell-action">
                                        @if ($podeEditar)
                                            <form method="POST" action="{{ route('embarques.baixar', $e) }}" style="margin:0;">
                                                @csrf @method('PATCH')
                                                <div style="display:flex; gap:6px; justify-content:flex-end; align-items:center;">
                                                    <input type="date" name="baixado_em" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}"
                                                           aria-label="Data da baixa de {{ $e->referencia() }}" style="padding:5px 8px; font-size:13px; width:auto;">
                                                    <button type="submit" class="btn btn-primary" style="padding:6px 12px; font-size:13px; white-space:nowrap;">Dar baixa</button>
                                                </div>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Venda sem logística nenhuma: aparece aqui para não ficar fora da agenda. --}}
    @if ($aProgramar->isNotEmpty())
        <div class="card" style="margin-bottom:22px;">
            <div class="card__header"><h2>Contratos com volume a programar</h2></div>
            <div class="card__body" style="padding:0;">
                <div class="table-wrap" style="border:0; border-radius:0;">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>UT</th>
                                <th>Comprador</th>
                                <th>Standard</th>
                                <th>Mês de embarque</th>
                                <th class="num">A programar (sc)</th>
                                <th class="num">Contrato (sc)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($aProgramar as $c)
                                <tr>
                                    <td><a href="{{ route('contratos.show', $c) }}"><strong>UT {{ $c->numero_ut }}</strong></a></td>
                                    <td>{{ \Illuminate\Support\Str::limit($c->cliente_nome, 30) }}</td>
                                    <td>{{ $c->standardLabel() ?? '—' }}</td>
                                    <td>{{ $c->embarque_mes ? $nomesMes[$c->embarque_mes->month - 1] . '/' . $c->embarque_mes->year : '—' }}</td>
                                    <td class="num"><strong>{{ $n($c->sacasAProgramar()) }}</strong></td>
                                    <td class="num">{{ $n($c->sacas) }}</td>
                                    <td class="cell-action">
                                        @if ($podeEditar)
                                            <a href="{{ route('embarques.create', $c) }}" class="btn btn-ghost" style="padding:6px 12px; font-size:13px; white-space:nowrap;">Programar</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <form method="GET" action="{{ route('embarques.index') }}" class="filter-bar">
        <div class="field">
            <label for="mes">Mês de faturamento</label>
            <input type="month" id="mes" name="mes" value="{{ $filtros['mes'] }}">
        </div>
        <div class="field">
            <label for="status">Etapa</label>
            <select id="status" name="status">
                <option value="{{ \App\Http\Controllers\Contratos\EmbarqueController::ABERTOS }}" @selected($filtros['status'] === 'abertos')>Em aberto (a sair)</option>
                <option value="aguardando_baixa" @selected($filtros['status'] === 'aguardando_baixa')>Faturados aguardando baixa</option>
                <option value="baixados" @selected($filtros['status'] === 'baixados')>Baixados (saíram do estoque)</option>
                <option value="todos" @selected($filtros['status'] === 'todos')>Todos, inclusive faturados</option>
                @foreach (\App\Models\Embarque::statuses() as $cod => $rotulo)
                    <option value="{{ $cod }}" @selected($filtros['status'] === $cod)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="armazem">Armazém</label>
            <select id="armazem" name="armazem">
                <option value="">Todos</option>
                @foreach (\App\Models\Armazem::lista() as $id => $nome)
                    <option value="{{ $id }}" @selected($filtros['armazem'] === (string) $id)>{{ $nome }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="busca">Buscar (UT, comprador ou ref.)</label>
            <input type="search" id="busca" name="busca" value="{{ $filtros['busca'] }}" placeholder="Ex.: 5973">
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        @unless ($semFiltro)
            <a href="{{ route('embarques.index') }}" class="btn btn-ghost">Limpar</a>
        @endunless
    </form>

    @forelse ($porMes as $chave => $doMes)
        @php
            [$ano, $mes] = explode('-', $chave);
            $sacasDoMes = $doMes->sum('sacas');
        @endphp
        <div class="card" style="margin-bottom:22px;">
            <div class="card__header">
                <h2>{{ $nomesMes[(int) $mes - 1] }}/{{ $ano }}
                    <span style="font-weight:400; color:var(--muted); font-size:13px;">
                        — {{ $doMes->count() === 1 ? '1 embarque' : $doMes->count() . ' embarques' }}, {{ $n($sacasDoMes) }} sc
                    </span>
                </h2>
            </div>
            <div class="card__body" style="padding:0;">
                <div class="table-wrap" style="border:0; border-radius:0;">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Ref.</th>
                                <th>Standard</th>
                                <th>Comprador</th>
                                <th>PSS</th>
                                <th>Etapa</th>
                                <th>Estufagem</th>
                                <th>Deadlines</th>
                                <th>Armazém</th>
                                <th>Destino / navio</th>
                                <th class="num">Sacas</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($doMes as $e)
                                @php $dias = $e->diasAteEstufagem(); @endphp
                                <tr @class(['linha-cancelada' => $e->baixado()])>
                                    <td>
                                        <a href="{{ route('contratos.show', $e->contrato) }}" style="white-space:nowrap;"><strong>{{ $e->referencia() }}</strong></a>
                                        @if ($e->contrato->buyer_ref)
                                            <br><span style="color:var(--muted); font-size:11.5px;">{{ $e->contrato->buyer_ref }}</span>
                                        @endif
                                    </td>
                                    <td style="white-space:nowrap;">{{ $e->contrato->standardLabel() ?? '—' }}</td>
                                    <td>
                                        {{ \Illuminate\Support\Str::limit($e->contrato->cliente_nome, 26) }}
                                        <br><span style="color:var(--muted); font-size:11.5px;">{{ $e->contrato->certificadoLabel() }}</span>
                                    </td>
                                    <td>
                                        @if ($e->pss)
                                            <span class="badge {{ $corPss[$e->pss] ?? 'badge--muted' }}" style="white-space:nowrap;" title="{{ $e->pss_obs }}">{{ \App\Models\Embarque::pssOpcoes()[$e->pss] ?? $e->pss }}</span>
                                        @else
                                            <span style="color:var(--muted);">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $corStatus[$e->status] ?? 'badge--muted' }}" style="white-space:nowrap;">{{ $e->statusLabel() }}</span>
                                        @if ($e->baixado())
                                            <br><span style="color:var(--muted); font-size:11.5px; white-space:nowrap;">baixa {{ $e->baixado_em->format('d/m/Y') }}</span>
                                        @elseif ($e->aguardandoBaixa())
                                            <br><span class="badge badge--amber" style="white-space:nowrap;">sem baixa</span>
                                        @endif
                                    </td>
                                    <td style="white-space:nowrap;">
                                        @if ($e->data_estufagem)
                                            <strong>{{ $e->data_estufagem->format('d/m') }}</strong>
                                            {{-- Só o que pede atenção ganha etiqueta: prazo
                                                 vencido ou a três dias. O resto é só a data. --}}
                                            @if ($dias !== null && $dias < 0)
                                                <br><span class="badge badge--red">atrasada {{ abs($dias) }}d</span>
                                            @elseif ($dias !== null && $dias <= 3)
                                                <br><span class="badge badge--amber">{{ $dias === 0 ? 'hoje' : 'em ' . $dias . 'd' }}</span>
                                            @endif
                                        @else
                                            <span style="color:var(--muted);">a agendar</span>
                                        @endif
                                        @if ($e->data_fumigacao)
                                            <br><span style="color:var(--muted); font-size:11.5px;">fumig. {{ $d($e->data_fumigacao) }}</span>
                                        @endif
                                    </td>
                                    {{-- Os dois prazos numa célula: lado a lado eram
                                         duas colunas de traços na maior parte das linhas. --}}
                                    <td style="white-space:nowrap; font-size:12.5px;">
                                        @if ($e->deadline_documentos || $e->deadline_carga)
                                            doc {{ $d($e->deadline_documentos) }}<br>carga {{ $d($e->deadline_carga) }}
                                        @else
                                            <span style="color:var(--muted);">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $e->armazem?->nome ?? '—' }}
                                        @if ($e->transportadora)
                                            <br><span style="color:var(--muted); font-size:11.5px;">{{ $e->transportadora }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $e->destino ?? '—' }}
                                        @if ($e->navio)
                                            <br><span style="color:var(--muted); font-size:11.5px;">{{ $e->navio }}</span>
                                        @endif
                                    </td>
                                    <td class="num"><strong>{{ $n($e->sacas) }}</strong></td>
                                    <td class="cell-action">
                                        @if ($podeEditar)
                                            <div style="display:flex; gap:6px; justify-content:flex-end; align-items:center;">
                                                <a href="{{ route('embarques.edit', $e) }}" class="btn btn-ghost" style="padding:6px 12px; font-size:13px; white-space:nowrap;">Editar</a>
                                                @unless ($e->encerrado())
                                                    <form method="POST" action="{{ route('embarques.faturar', $e) }}" style="margin:0;"
                                                          onsubmit="return confirm('Marcar {{ $e->referencia() }} como FATURADO hoje? As {{ $n($e->sacas) }} sacas continuam na posição até a baixa do estoque.');">
                                                        @csrf @method('PATCH')
                                                        <button type="submit" class="btn btn-ghost" style="padding:6px 12px; font-size:13px; white-space:nowrap;">Faturar</button>
                                                    </form>
                                                @endunless
                                                @if ($e->aguardandoBaixa())
                                                    <form method="POST" action="{{ route('embarques.baixar', $e) }}" style="margin:0;"
                                                          onsubmit="return confirm('Dar baixa de {{ $e->referencia() }} com a data de hoje? As {{ $n($e->sacas) }} sacas saem do estoque e da posição de vendas.');">
                                                        @csrf @method('PATCH')
                                                        <button type="submit" class="btn btn-ghost" style="padding:6px 12px; font-size:13px; white-space:nowrap;">Dar baixa</button>
                                                    </form>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card__body" style="text-align:center; color:var(--muted); padding:28px;">
                {{ $semFiltro ? 'Nenhum embarque em aberto. Programe o primeiro a partir de um contrato.' : 'Nenhum embarque neste filtro.' }}
            </div>
        </div>
    @endforelse
@endsection
