@extends('layouts.app')

@section('title', 'Classificação — ' . $compra->uts)

@section('content')
    <div class="card" style="max-width:820px;">
        <div class="card__header card__header--dark mesh-texture">
            <h2>Classificação da compra {{ $compra->uts }}</h2>
        </div>
        <div class="card__body">
            <p style="color:var(--muted); font-size:13.5px; margin-top:0;">
                Volume entregue: <strong>{{ number_format($compra->volume_sacas, 2, ',', '.') }} sacas</strong>.
                Preencha a % de cada peneira — o campo de sacas é preenchido automaticamente
                (você pode ajustar manualmente). A quantidade de lotes é calculada pelo
                servidor (total de sacas ÷ 283,49) e a soma das % deve fechar em 100%.
            </p>

            <form method="POST" action="{{ route('compras.classificacao.update', $compra) }}" id="form-classificacao" novalidate>
                @csrf
                @method('PUT')

                <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:18px;">
                    <div class="field {{ $errors->has('padrao_final') ? 'has-error' : '' }}" style="flex:1; min-width:250px; margin-bottom:0;">
                        <label for="padrao_final">Padrão final</label>
                        <select id="padrao_final" name="padrao_final" required>
                            <option value="">Selecione...</option>
                            @foreach (\App\Models\Classificacao::padroes() as $cod => $rotulo)
                                <option value="{{ $cod }}" @selected(old('padrao_final', $classificacao?->padrao_final) === $cod)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        @error('padrao_final') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('tipo_bebida') ? 'has-error' : '' }}" style="flex:1; min-width:250px; margin-bottom:0;">
                        <label for="tipo_bebida">Tipo de bebida</label>
                        <select id="tipo_bebida" name="tipo_bebida" required>
                            <option value="">Selecione...</option>
                            @foreach (\App\Models\Classificacao::tiposBebida() as $cod => $rotulo)
                                <option value="{{ $cod }}" @selected(old('tipo_bebida', $classificacao?->tipo_bebida) === $cod)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        @error('tipo_bebida') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="data" id="tabela-peneiras">
                        <thead>
                            <tr><th>Peneira</th><th>%</th><th>Sacas</th></tr>
                        </thead>
                        <tbody>
                            @php
                                $linhas = [
                                    ['peneira_1718', 'SCS 17/18'],
                                    ['peneira_1416', 'SCS 14/16'],
                                    ['mercado_interno', 'Mercado interno'],
                                    ['grinders', 'Grinders'],
                                    ['moka', 'Moka'],
                                ];
                            @endphp
                            @foreach ($linhas as [$prefixo, $label])
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td>
                                        <input type="number" step="0.01" min="0" max="100"
                                               class="js-pct" data-target="{{ $prefixo }}_sacas"
                                               name="{{ $prefixo }}_pct"
                                               value="{{ old($prefixo . '_pct', $classificacao?->{$prefixo . '_pct'} ?? 0) }}">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0"
                                               id="{{ $prefixo }}_sacas" name="{{ $prefixo }}_sacas"
                                               value="{{ old($prefixo . '_sacas', $classificacao?->{$prefixo . '_sacas'} ?? 0) }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p style="font-size:13px; color:var(--muted); margin-top:10px;">
                    Total de % informado: <strong id="soma-pct">0</strong>% —
                    Total de sacas: <strong id="soma-sacas">0</strong>
                </p>

                <div style="margin-top:16px; display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary">Salvar classificação</button>
                    <a href="{{ route('compras.show', $compra) }}" class="btn btn-ghost">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Conveniência de interface apenas: preenche "sacas" a partir da %
        // informada. O servidor SEMPRE recalcula a quantidade de lotes e
        // valida a soma — este script não substitui a validação real.
        (function () {
            var volumeTotal = {{ (float) $compra->volume_sacas }};
            var pctInputs = document.querySelectorAll('.js-pct');
            var somaPctEl = document.getElementById('soma-pct');
            var somaSacasEl = document.getElementById('soma-sacas');

            function recalcular() {
                var somaPct = 0, somaSacas = 0;
                pctInputs.forEach(function (input) {
                    somaPct += parseFloat(input.value || 0);
                    var sacasInput = document.getElementById(input.dataset.target);
                    somaSacas += parseFloat(sacasInput.value || 0);
                });
                somaPctEl.textContent = somaPct.toFixed(2);
                somaSacasEl.textContent = somaSacas.toFixed(2);
            }

            pctInputs.forEach(function (input) {
                input.addEventListener('input', function () {
                    var sacasInput = document.getElementById(input.dataset.target);
                    var pct = parseFloat(input.value || 0);
                    sacasInput.value = ((pct / 100) * volumeTotal).toFixed(2);
                    recalcular();
                });
            });

            document.querySelectorAll('#tabela-peneiras input[type=number]').forEach(function (el) {
                el.addEventListener('input', recalcular);
            });

            recalcular();
        })();
    </script>
@endsection
