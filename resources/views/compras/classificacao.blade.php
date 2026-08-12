@extends('layouts.app')

@section('title', 'Classificação — ' . $compra->uts)

@section('content')
    <div class="card" style="max-width:820px;">
        <div class="card__header card__header--dark mesh-texture">
            <h2>Classificação da compra {{ $compra->uts }}</h2>
        </div>
        <div class="card__body">
            <p style="color:var(--muted); font-size:13.5px; margin-top:0;">
                Volume da UTS: <strong>{{ number_format(max((float) $compra->volume_contratado, $compra->sacasEntregues()), 2, ',', '.') }} sacas</strong>
                (contratado {{ number_format((float) $compra->volume_contratado, 2, ',', '.') }} ·
                entregue {{ number_format($compra->sacasEntregues(), 2, ',', '.') }}).
                Preencha a % de cada peneira — o campo de sacas é preenchido automaticamente
                (você pode ajustar manualmente). A quantidade de lotes é calculada pelo
                servidor (total de sacas ÷ 283,49) e a soma das % deve fechar em 100%.
            </p>

            <form method="POST" action="{{ route('compras.classificacao.update', $compra) }}" id="form-classificacao" novalidate>
                @csrf
                @method('PUT')

                {{-- Padrão e bebida já vêm do lançamento da compra: aqui eles
                     aparecem preenchidos e podem ser corrigidos se a
                     conferência discordar do que foi negociado. Conilon não
                     tem nenhum dos dois. --}}
                @unless ($compra->ehConilon())
                    <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:18px;">
                        <div class="field {{ $errors->has('padrao_final') ? 'has-error' : '' }}" style="flex:1; min-width:250px; margin-bottom:0;">
                            <label for="padrao_final">Padrão final</label>
                            <select id="padrao_final" name="padrao_final" required>
                                <option value="">Selecione...</option>
                                @foreach (\App\Models\Classificacao::padroes() as $cod => $rotulo)
                                    <option value="{{ $cod }}" @selected(old('padrao_final', $classificacao?->padrao_final ?? $compra->padrao_final) === $cod)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            @error('padrao_final') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="field {{ $errors->has('tipo_bebida') ? 'has-error' : '' }}" style="flex:1; min-width:250px; margin-bottom:0;">
                            <label for="tipo_bebida">Tipo de bebida</label>
                            <select id="tipo_bebida" name="tipo_bebida" required>
                                <option value="">Selecione...</option>
                                @foreach (\App\Models\Classificacao::tiposBebida() as $cod => $rotulo)
                                    <option value="{{ $cod }}" @selected(old('tipo_bebida', $classificacao?->tipo_bebida ?? $compra->tipo_bebida) === $cod)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            @error('tipo_bebida') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                @else
                    <p class="alert" style="background:var(--primary-soft); color:var(--primary); margin-bottom:18px;">
                        Compra de <strong>conilon</strong>: não tem padrão final nem tipo de bebida —
                        preencha só a distribuição abaixo.
                    </p>
                @endunless

                {{-- Erros de soma (100% e teto de sacas): aparecem aqui, acima
                     da tabela, porque são do conjunto e não de uma peneira. --}}
                @error('soma_pct') <div class="alert alert-error">{{ $message }}</div> @enderror
                @error('soma_sacas') <div class="alert alert-error">{{ $message }}</div> @enderror

                <div class="table-wrap">
                    <table class="data" id="tabela-peneiras">
                        <thead>
                            <tr><th>Peneira</th><th>%</th><th>Sacas</th></tr>
                        </thead>
                        <tbody>
                            {{-- Lista central em Classificacao::faixas(): incluir
                                 uma peneira lá já traz a linha para cá, com o
                                 cálculo automático de sacas funcionando. --}}
                            @foreach (\App\Models\Classificacao::faixas() as $prefixo => $label)
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
            var volumeTotal = {{ max((float) $compra->volume_contratado, $compra->sacasEntregues()) }};
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
