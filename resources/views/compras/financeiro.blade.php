@extends('layouts.app')

@section('title', 'Financeiro — ' . $compra->uts)

@section('content')
    <div class="card" style="max-width:640px;">
        <div class="card__header"><h2>Financeiro da compra {{ $compra->uts }}</h2></div>
        <div class="card__body">
            <p style="color:var(--muted); font-size:13.5px; margin-top:0;">
                Volume desta compra: <strong>{{ number_format($compra->volume_sacas, 2, ',', '.') }} sacas</strong>.
                O valor total é calculado automaticamente pelo servidor (valor da saca × volume).
            </p>

            <form method="POST" action="{{ route('compras.financeiro.update', $compra) }}" novalidate>
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="field {{ $errors->has('valor_saca') ? 'has-error' : '' }}">
                        <label for="valor_saca">Valor da saca (R$)</label>
                        <input type="number" step="0.01" min="0" id="valor_saca" name="valor_saca"
                               value="{{ old('valor_saca', $financeiro?->valor_saca) }}" required>
                        @error('valor_saca') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label>Valor total (calculado)</label>
                        <input type="text" id="valor_total_preview"
                               value="R$ {{ number_format($financeiro?->valor_total ?? 0, 2, ',', '.') }}" disabled>
                    </div>

                    <div class="field {{ $errors->has('corretor_nome') ? 'has-error' : '' }}">
                        <label for="corretor_nome">Corretor</label>
                        <input type="text" id="corretor_nome" name="corretor_nome" value="{{ old('corretor_nome', $financeiro?->corretor_nome) }}">
                        @error('corretor_nome') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('comissao_pct') ? 'has-error' : '' }}">
                        <label for="comissao_pct">Comissão (%)</label>
                        <input type="number" step="0.01" min="0" max="100" id="comissao_pct" name="comissao_pct" value="{{ old('comissao_pct', $financeiro?->comissao_pct) }}">
                        @error('comissao_pct') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div style="margin-top:20px; display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary">Salvar financeiro</button>
                    <a href="{{ route('compras.show', $compra) }}" class="btn btn-ghost">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const volume = {{ (float) $compra->volume_sacas }};
            const campoValorSaca = document.getElementById('valor_saca');
            const campoTotal = document.getElementById('valor_total_preview');

            function recalcular() {
                const valorSaca = parseFloat(campoValorSaca.value) || 0;
                campoTotal.value = (valorSaca * volume).toLocaleString('pt-BR', {
                    style: 'currency',
                    currency: 'BRL',
                });
            }

            campoValorSaca.addEventListener('input', recalcular);
            recalcular(); // já mostra o valor certo ao abrir a tela
        })();
    </script>
@endsection