@extends('layouts.app')

@section('title', 'Nova compra')

@section('content')
    <div class="card" style="max-width:780px;">
        <div class="card__header"><h2>Dados de entrada da compra</h2></div>
        <div class="card__body">
            <form method="POST" action="{{ route('compras.store') }}" novalidate>
                @csrf

                <div class="form-grid">
                    <div class="field {{ $errors->has('uts') ? 'has-error' : '' }}">
                        <label for="uts">UTS (ref. de compra)</label>
                        <input type="text" id="uts" name="uts" value="{{ old('uts') }}" required>
                        @error('uts') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('mes_ano') ? 'has-error' : '' }}">
                        <label for="mes_ano">Mês/ano da entrega</label>
                        <input type="month" id="mes_ano" name="mes_ano" value="{{ old('mes_ano') }}" required>
                        @error('mes_ano') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('fornecedor_nome') ? 'has-error' : '' }}">
                        <label for="fornecedor_nome">Fornecedor (nome)</label>
                        <input type="text" id="fornecedor_nome" name="fornecedor_nome" value="{{ old('fornecedor_nome') }}" required>
                        @error('fornecedor_nome') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('fornecedor_cnpj') ? 'has-error' : '' }}">
                        <label for="fornecedor_cnpj">Fornecedor (CNPJ)</label>
                        <input type="text" id="fornecedor_cnpj" name="fornecedor_cnpj" value="{{ old('fornecedor_cnpj') }}" placeholder="00.000.000/0000-00" required>
                        @error('fornecedor_cnpj') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('armazem') ? 'has-error' : '' }}">
                        <label for="armazem">Armazém de entrega</label>
                        <select id="armazem" name="armazem" required>
                            <option value="">Selecione...</option>
                            @foreach ($armazens as $valor => $label)
                                <option value="{{ $valor }}" @selected(old('armazem') === $valor)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('armazem') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('certificacao') ? 'has-error' : '' }}">
                        <label for="certificacao">Certificação</label>
                        <select id="certificacao" name="certificacao" required>
                            <option value="">Selecione...</option>
                            @foreach ($certificacoes as $valor => $label)
                                <option value="{{ $valor }}" @selected(old('certificacao') === $valor)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('certificacao') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('tipo_entrada') ? 'has-error' : '' }}">
                        <label for="tipo_entrada">Tipo padrão de entrada</label>
                        <input type="text" id="tipo_entrada" name="tipo_entrada" value="{{ old('tipo_entrada', 'BICA') }}">
                        <span class="hint">Assume "BICA" como entrada inicial.</span>
                    </div>

                    <div class="field {{ $errors->has('volume_sacas') ? 'has-error' : '' }}">
                        <label for="volume_sacas">Volume entregue (sacas)</label>
                        <input type="number" step="0.01" min="0.01" id="volume_sacas" name="volume_sacas" value="{{ old('volume_sacas') }}" required>
                        @error('volume_sacas') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div style="margin-top:20px; display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary">Registrar compra</button>
                    <a href="{{ route('compras.index') }}" class="btn btn-ghost">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
