@extends('layouts.app')

@php
    $editando = $embarque->exists;
    $valor = fn (string $campo) => old($campo, $embarque->$campo);
    $data = fn (string $campo) => old($campo, $embarque->$campo?->format('Y-m-d'));
    $n = fn ($v) => number_format((float) $v, 0, ',', '.');
@endphp

@section('title', $editando ? 'Editar embarque — ' . $embarque->referencia() : 'Novo embarque — UT ' . $contrato->numero_ut)
@section('subtitle', $contrato->cliente_nome . ' · ' . ($contrato->standardLabel() ?? 'sem standard') . ' · contrato de ' . $n($contrato->quantidade_kg) . ' kg (' . $n($contrato->sacas) . ' sc)')

@section('crumb')
    <span>Exportação</span><span class="sep">/</span>
    <a href="{{ route('embarques.index') }}" style="color:inherit;">Controle logístico</a><span class="sep">/</span>
    <b>{{ $editando ? 'Editar' : 'Novo' }}</b>
@endsection

@section('content')
    {{-- Programação de estufagem: o PDF que o armazém imprime e o e-mail que
         leva esse PDF em anexo. Só existe depois de salvo. --}}
    @if ($programacao)
        <div class="card" style="max-width:920px; margin-bottom:20px;">
            <div class="card__header"><h2>Programação de estufagem</h2></div>
            <div class="card__body">
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <a href="{{ route('embarques.programacao', $embarque) }}" class="btn btn-primary">↓ Baixar PDF da programação</a>
                    @if ($programacao->enviavel())
                        <a href="{{ $programacao->mailto() }}" class="btn btn-ghost">✉ Abrir e-mail no Outlook</a>
                    @endif
                </div>
                <p style="margin:12px 0 0; font-size:12.5px; color:var(--muted);">
                    Baixe o PDF e anexe ao e-mail: o Outlook abre com destinatários, assunto e um resumo, mas
                    um link não consegue anexar arquivo. O PDF usa os dados <strong>já salvos</strong> — salve as
                    alterações antes de gerar.
                </p>
                @if ($programacao->enviavel())
                    <p style="margin:8px 0 0; font-size:12.5px; color:var(--muted);">
                        Para: {{ implode('; ', $programacao->para) }}
                        @if ($programacao->copia) <br>Cópia: {{ implode('; ', $programacao->copia) }} @endif
                    </p>
                @endif
                @foreach ($programacao->avisos as $aviso)
                    <div class="aa-flash__aviso">⚠ {{ $aviso }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <form method="POST" action="{{ $editando ? route('embarques.update', $embarque) : route('embarques.store', $contrato) }}" novalidate>
        @csrf
        @if ($editando) @method('PUT') @endif

        <div class="card" style="max-width:920px; margin-bottom:20px;">
            <div class="card__header"><h2>1. Parcela do contrato</h2></div>
            <div class="card__body">
                <div class="form-grid form-grid--3">
                    <div class="field {{ $errors->has('quantidade_kg') ? 'has-error' : '' }}">
                        <label for="quantidade_kg">Quantidade (kg)</label>
                        <input type="number" step="0.01" min="1" id="quantidade_kg" name="quantidade_kg" value="{{ $valor('quantidade_kg') }}" required>
                        {{-- O saldo desconta os outros embarques do contrato; na edição,
                             o próprio embarque não conta contra si mesmo. --}}
                        <span class="hint">
                            Falta programar no contrato:
                            {{ $n($contrato->kgAProgramar() + ($editando ? (float) $embarque->quantidade_kg : 0)) }} kg.
                        </span>
                        @error('quantidade_kg') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('mes_faturamento') ? 'has-error' : '' }}">
                        <label for="mes_faturamento">Mês de faturamento</label>
                        <input type="month" id="mes_faturamento" name="mes_faturamento"
                               value="{{ old('mes_faturamento', $embarque->mes_faturamento?->format('Y-m')) }}" required>
                        <span class="hint">É o mês em que a venda pesa na posição.</span>
                        @error('mes_faturamento') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('sufixo') ? 'has-error' : '' }}">
                        <label for="sufixo">Sufixo <span class="hint">(opcional)</span></label>
                        <input type="text" id="sufixo" name="sufixo" value="{{ $valor('sufixo') }}" maxlength="5" placeholder="A, B, C…">
                        <span class="hint">Só quando o contrato sai em mais de um embarque.</span>
                        @error('sufixo') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="max-width:920px; margin-bottom:20px;">
            <div class="card__header"><h2>2. Andamento</h2></div>
            <div class="card__body">
                <div class="form-grid form-grid--3">
                    <div class="field {{ $errors->has('status') ? 'has-error' : '' }}">
                        <label for="status">Etapa</label>
                        <select id="status" name="status" required>
                            @foreach (\App\Models\Embarque::statuses() as $cod => $rotulo)
                                <option value="{{ $cod }}" @selected($valor('status') === $cod)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        @error('status') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('faturado_em') ? 'has-error' : '' }}">
                        <label for="faturado_em">Data do faturamento</label>
                        <input type="date" id="faturado_em" name="faturado_em" value="{{ $data('faturado_em') }}">
                        <span class="hint">Só vale com a etapa Faturado ou Embarcado; em branco, entra a data de hoje.</span>
                        @error('faturado_em') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('baixado_em') ? 'has-error' : '' }}">
                        <label for="baixado_em">Baixa do estoque</label>
                        <input type="date" id="baixado_em" name="baixado_em" value="{{ $data('baixado_em') }}" max="{{ now()->toDateString() }}">
                        <span class="hint">Data em que a saída foi registrada (Digisul). É a baixa, não a nota, que tira a venda da posição. Em branco = ainda no estoque.</span>
                        @error('baixado_em') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('pss') ? 'has-error' : '' }}">
                        <label for="pss">PSS <span class="hint">(amostra pré-embarque)</span></label>
                        <select id="pss" name="pss">
                            <option value="">— não se aplica —</option>
                            @foreach (\App\Models\Embarque::pssOpcoes() as $cod => $rotulo)
                                <option value="{{ $cod }}" @selected($valor('pss') === $cod)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        @error('pss') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field field--full {{ $errors->has('pss_obs') ? 'has-error' : '' }}" style="grid-column:1 / -1;">
                        <label for="pss_obs">Observação da PSS <span class="hint">(opcional)</span></label>
                        <input type="text" id="pss_obs" name="pss_obs" value="{{ $valor('pss_obs') }}" maxlength="120" placeholder="Ex.: enviada dia 22/10 — DHL 8584673705">
                        @error('pss_obs') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="max-width:920px; margin-bottom:20px;">
            <div class="card__header"><h2>3. Estufagem e prazos</h2></div>
            <div class="card__body">
                <div class="form-grid form-grid--3">
                    @foreach ([
                        'data_estufagem' => 'Estufagem (stuff)',
                        'data_fitossanitario' => 'Fitossanitário',
                        'deadline_documentos' => 'Deadline de documentos',
                        'deadline_carga' => 'Deadline de carga',
                        'etd' => 'ETD',
                    ] as $campo => $rotulo)
                        <div class="field {{ $errors->has($campo) ? 'has-error' : '' }}">
                            <label for="{{ $campo }}">{{ $rotulo }}</label>
                            <input type="date" id="{{ $campo }}" name="{{ $campo }}" value="{{ $data($campo) }}">
                            @error($campo) <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    @endforeach

                    <div class="field {{ $errors->has('armazem_id') ? 'has-error' : '' }}">
                        <label for="armazem_id">Armazém de saída</label>
                        <select id="armazem_id" name="armazem_id">
                            <option value="">— a definir —</option>
                            @foreach ($armazens as $id => $nome)
                                <option value="{{ $id }}" @selected((string) $valor('armazem_id') === (string) $id)>{{ $nome }}</option>
                            @endforeach
                        </select>
                        @error('armazem_id') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('transportadora') ? 'has-error' : '' }}">
                        <label for="transportadora">Transportadora</label>
                        <input type="text" id="transportadora" name="transportadora" value="{{ $valor('transportadora') }}" maxlength="60" list="lista-transportadoras">
                        {{-- Sugere do cadastro, mas aceita digitar: o embarque guarda
                             o nome, e os e-mails são achados por ele. --}}
                        <datalist id="lista-transportadoras">
                            @foreach ($transportadoras as $nome)
                                <option value="{{ $nome }}"></option>
                            @endforeach
                        </datalist>
                        @error('transportadora') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('pilha') ? 'has-error' : '' }}">
                        <label for="pilha">Pilha <span class="hint">(lote preparado)</span></label>
                        <input type="text" id="pilha" name="pilha" value="{{ $valor('pilha') }}" maxlength="80" placeholder="Ex.: PI9074/26">
                        @error('pilha') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('ico') ? 'has-error' : '' }}">
                        <label for="ico">ICO</label>
                        <input type="text" id="ico" name="ico" value="{{ $valor('ico') }}" maxlength="40" placeholder="Ex.: 1007 + 1008">
                        @error('ico') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('instrucoes') ? 'has-error' : '' }}" style="grid-column:1 / -1;">
                        <label for="instrucoes">Instruções para o armazém <span class="hint">(saem em destaque no PDF da programação)</span></label>
                        <textarea id="instrucoes" name="instrucoes" rows="2" style="width:100%; resize:vertical;"
                                  placeholder="Ex.: É necessário utilizar kit forração. Enviar fotos no começo, meio e final da estufagem.">{{ $valor('instrucoes') }}</textarea>
                        @error('instrucoes') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="max-width:920px; margin-bottom:20px;">
            <div class="card__header"><h2>4. Fumigação <span style="font-weight:400; color:var(--muted); font-size:13px;">— em branco quando não há</span></h2></div>
            <div class="card__body">
                <div class="form-grid form-grid--3">
                    <div class="field {{ $errors->has('data_fumigacao') ? 'has-error' : '' }}">
                        <label for="data_fumigacao">Data da fumigação</label>
                        <input type="date" id="data_fumigacao" name="data_fumigacao" value="{{ $data('data_fumigacao') }}">
                        @error('data_fumigacao') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    @foreach ([
                        'fumigacao_empresa' => ['Empresa', 'Ex.: Nikkey', 60],
                        'fumigacao_tipo' => ['Tipo de fumigação', 'Ex.: Câmara de lona', 60],
                        'fumigacao_tempo' => ['Tempo', 'Ex.: 120H', 20],
                        'permit_import' => ['Permit import', 'N/A', 40],
                    ] as $campo => [$rotulo, $exemplo, $max])
                        <div class="field {{ $errors->has($campo) ? 'has-error' : '' }}">
                            <label for="{{ $campo }}">{{ $rotulo }}</label>
                            <input type="text" id="{{ $campo }}" name="{{ $campo }}" value="{{ $valor($campo) }}" maxlength="{{ $max }}" placeholder="{{ $exemplo }}">
                            @error($campo) <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card" style="max-width:920px; margin-bottom:20px;">
            <div class="card__header"><h2>5. Navio</h2></div>
            <div class="card__body">
                <div class="form-grid form-grid--3">
                    <div class="field {{ $errors->has('destino') ? 'has-error' : '' }}">
                        <label for="destino">Destino</label>
                        <input type="text" id="destino" name="destino" value="{{ $valor('destino') }}" maxlength="80" placeholder="Ex.: HAMBURG">
                        @error('destino') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field {{ $errors->has('navio') ? 'has-error' : '' }}">
                        <label for="navio">Navio</label>
                        <input type="text" id="navio" name="navio" value="{{ $valor('navio') }}" maxlength="80" placeholder="Ex.: MAERSK LA PAZ">
                        @error('navio') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field {{ $errors->has('booking') ? 'has-error' : '' }}">
                        <label for="booking">Booking</label>
                        <input type="text" id="booking" name="booking" value="{{ $valor('booking') }}" maxlength="120" placeholder="Ex.: 280836851 - MAERSK">
                        @error('booking') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field {{ $errors->has('observacoes') ? 'has-error' : '' }}" style="grid-column:1 / -1;">
                        <label for="observacoes">Observações</label>
                        <textarea id="observacoes" name="observacoes" rows="3" style="width:100%; resize:vertical;">{{ $valor('observacoes') }}</textarea>
                        @error('observacoes') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:10px; max-width:920px;">
            <button type="submit" class="btn btn-primary">{{ $editando ? 'Salvar alterações' : 'Lançar embarque' }}</button>
            <a href="{{ $editando ? route('embarques.index') : route('contratos.show', $contrato) }}" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>

    @if ($editando)
        <form method="POST" action="{{ route('embarques.destroy', $embarque) }}" style="margin-top:26px; max-width:920px;"
              onsubmit="return confirm('Excluir o embarque {{ $embarque->referencia() }}? O volume volta para o saldo a programar do contrato.');">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-ghost" style="color:var(--danger-text);">Excluir este embarque</button>
        </form>
    @endif
@endsection
