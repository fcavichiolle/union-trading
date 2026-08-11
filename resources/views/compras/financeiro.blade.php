@extends('layouts.app')

@section('title', 'Financeiro — ' . $compra->uts)

@section('crumb')
    <span>Compras &amp; Classificação</span><span class="sep">/</span>
    <a href="{{ route('compras.show', $compra) }}" style="color:inherit;">{{ $compra->uts }}</a><span class="sep">/</span>
    <b>Financeiro</b>
@endsection

@section('content')
    <div class="card" style="max-width:680px;">
        <div class="card__header"><h2>Financeiro da compra {{ $compra->uts }}</h2></div>
        <div class="card__body">
            <p style="color:var(--muted); font-size:13.5px; margin-top:0;">
                Contratado: <strong>{{ number_format((float) $compra->volume_contratado, 2, ',', '.') }} sacas</strong> ·
                já entregue: <strong>{{ number_format($compra->sacasEntregues(), 2, ',', '.') }} sacas</strong>.
                Paga-se pelo que realmente entrou — por isso os dois totais aparecem abaixo.
            </p>

            <form method="POST" action="{{ route('compras.financeiro.update', $compra) }}" novalidate>
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="field {{ $errors->has('valor_saca') ? 'has-error' : '' }}">
                        <label for="valor_saca">Valor da saca (R$)</label>
                        <input type="number" step="0.01" min="0" id="valor_saca" name="valor_saca"
                               value="{{ old('valor_saca', $compra->valor_saca) }}" required>
                        @error('valor_saca') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label>Valor contratado (calculado)</label>
                        <input type="text" id="total_contratado" disabled>
                    </div>

                    <div class="field">
                        <label>Valor efetivo — entregue (calculado)</label>
                        <input type="text" id="total_entregue" disabled>
                    </div>

                    <div class="field {{ $errors->has('corretor_nome') ? 'has-error' : '' }}">
                        <label for="corretor_nome">Corretor</label>
                        <input type="text" id="corretor_nome" name="corretor_nome" value="{{ old('corretor_nome', $compra->corretor_nome) }}">
                        @error('corretor_nome') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('comissao_pct') ? 'has-error' : '' }}">
                        <label for="comissao_pct">Comissão (%)</label>
                        <input type="number" step="0.01" min="0" max="100" id="comissao_pct" name="comissao_pct"
                               value="{{ old('comissao_pct', $compra->comissao_pct) }}">
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
        // Prévia dos dois totais. O valor oficial é sempre recalculado no
        // servidor (Compra::valorContratado / valorEntregue).
        (function () {
            var contratado = {{ (float) $compra->volume_contratado }};
            var entregue = {{ $compra->sacasEntregues() }};
            var campo = document.getElementById('valor_saca');
            var elContratado = document.getElementById('total_contratado');
            var elEntregue = document.getElementById('total_entregue');

            function moeda(v) {
                return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            }

            function recalcular() {
                var preco = parseFloat(campo.value) || 0;
                elContratado.value = moeda(preco * contratado);
                elEntregue.value = moeda(preco * entregue);
            }

            campo.addEventListener('input', recalcular);
            recalcular();
        })();
    </script>
@endsection
