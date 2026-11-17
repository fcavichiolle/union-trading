@extends('layouts.app')

@section('title', 'Agendamentos')
@section('subtitle', 'Estufagens em aberto na ordem em que acontecem. O que já foi faturado sai daqui sozinho.')

@section('crumb')
    <span>Exportação</span><span class="sep">/</span><b>Agendamentos</b>
@endsection

@php
    $n = fn ($v) => number_format((float) $v, 0, ',', '.');
    $d = fn ($data) => $data ? $data->format('d/m') : '—';
    $diasSemana = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
    $corStatus = [
        'ABERTO' => 'badge--muted', 'AG_BOOKING' => 'badge--amber', 'BOOKING' => 'badge--green',
        'AG_SI' => 'badge--amber', 'PROGRAMADO' => 'badge--green',
    ];
    $hoje = now()->startOfDay();
@endphp

@section('content')
    <form method="GET" action="{{ route('embarques.agenda') }}" class="filter-bar">
        <div class="field">
            <label for="armazem">Armazém</label>
            <select id="armazem" name="armazem">
                <option value="">Todos</option>
                @foreach (\App\Models\Armazem::lista() as $id => $nome)
                    <option value="{{ $id }}" @selected($armazem === (string) $id)>{{ $nome }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        @if ($armazem !== '')
            <a href="{{ route('embarques.agenda') }}" class="btn btn-ghost">Limpar</a>
        @endif
        <a href="{{ route('embarques.index') }}" class="btn btn-ghost">Controle logístico →</a>
    </form>

    @forelse ($porDia as $dia => $doDia)
        @php
            $data = \Illuminate\Support\Carbon::parse($dia);
            $dias = (int) $hoje->diffInDays($data, false);
        @endphp
        <div class="card" style="margin-bottom:18px;">
            <div class="card__header">
                <h2>
                    {{ $data->format('d/m/Y') }}
                    <span style="font-weight:400; color:var(--muted); font-size:13px;">
                        {{ $diasSemana[$data->dayOfWeek] }} — {{ $doDia->count() === 1 ? '1 estufagem' : $doDia->count() . ' estufagens' }}, {{ $n($doDia->sum('sacas')) }} sc
                    </span>
                    {{-- Só o que pede atenção ganha etiqueta. --}}
                    @if ($dias < 0)
                        <span class="badge badge--red" style="margin-left:6px;">atrasada {{ abs($dias) }}d</span>
                    @elseif ($dias === 0)
                        <span class="badge badge--amber" style="margin-left:6px;">hoje</span>
                    @elseif ($dias <= 3)
                        <span class="badge badge--amber" style="margin-left:6px;">em {{ $dias }}d</span>
                    @endif
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
                                <th>Armazém</th>
                                <th>Transportadora</th>
                                <th>Fumig.</th>
                                <th>DL doc</th>
                                <th>DL carga</th>
                                <th>Etapa</th>
                                <th class="num">Sacas</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($doDia as $e)
                                <tr>
                                    <td>
                                        <a href="{{ route('contratos.show', $e->contrato) }}" style="white-space:nowrap;"><strong>{{ $e->referencia() }}</strong></a>
                                        @if ($e->pilha)
                                            <br><span style="color:var(--muted); font-size:11.5px;">{{ $e->pilha }}</span>
                                        @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($e->contrato->cliente_nome, 28) }}</td>
                                    <td style="white-space:nowrap;">{{ $e->contrato->standardLabel() ?? '—' }}</td>
                                    <td>{{ $e->armazem?->nome ?? '—' }}</td>
                                    <td>{{ $e->transportadora ?? '—' }}</td>
                                    <td>{{ $d($e->data_fumigacao) }}</td>
                                    <td>{{ $d($e->deadline_documentos) }}</td>
                                    <td>{{ $d($e->deadline_carga) }}</td>
                                    <td><span class="badge {{ $corStatus[$e->status] ?? 'badge--muted' }}" style="white-space:nowrap;">{{ $e->statusLabel() }}</span></td>
                                    <td class="num"><strong>{{ $n($e->sacas) }}</strong></td>
                                    <td class="cell-action">
                                        @if ($podeEditar)
                                            <div style="display:flex; gap:6px; justify-content:flex-end;">
                                                {{-- PDF da programação: o que o armazém imprime. --}}
                                                <a href="{{ route('embarques.programacao', $e) }}" class="btn btn-ghost" style="padding:6px 12px; font-size:13px; white-space:nowrap;"
                                                   title="Baixar o PDF da programação de estufagem">↓ Programação</a>
                                                <a href="{{ route('embarques.edit', $e) }}" class="btn btn-ghost" style="padding:6px 12px; font-size:13px;">Editar</a>
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
        <div class="card" style="margin-bottom:18px;">
            <div class="card__body" style="text-align:center; color:var(--muted); padding:28px;">
                Nenhuma estufagem agendada.
            </div>
        </div>
    @endforelse

    {{-- Embarque em aberto sem data: não tem dia para aparecer acima, mas
         precisa estar na tela — é justamente o que falta agendar. --}}
    @if ($semData->isNotEmpty())
        <div class="card">
            <div class="card__header">
                <h2>Sem data de estufagem
                    <span style="font-weight:400; color:var(--muted); font-size:13px;">
                        — {{ $semData->count() === 1 ? '1 embarque' : $semData->count() . ' embarques' }} a agendar, {{ $n($semData->sum('sacas')) }} sc
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
                                <th>Faturamento</th>
                                <th>Etapa</th>
                                <th class="num">Sacas</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($semData->sortBy('mes_faturamento') as $e)
                                <tr>
                                    <td><a href="{{ route('contratos.show', $e->contrato) }}" style="white-space:nowrap;"><strong>{{ $e->referencia() }}</strong></a></td>
                                    <td>{{ \Illuminate\Support\Str::limit($e->contrato->cliente_nome, 32) }}</td>
                                    <td style="white-space:nowrap;">{{ $e->contrato->standardLabel() ?? '—' }}</td>
                                    <td>{{ $e->mes_faturamento->format('m/Y') }}</td>
                                    <td><span class="badge {{ $corStatus[$e->status] ?? 'badge--muted' }}" style="white-space:nowrap;">{{ $e->statusLabel() }}</span></td>
                                    <td class="num"><strong>{{ $n($e->sacas) }}</strong></td>
                                    <td class="cell-action">
                                        @if ($podeEditar)
                                            <a href="{{ route('embarques.edit', $e) }}" class="btn btn-ghost" style="padding:6px 12px; font-size:13px;">Agendar</a>
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
@endsection
