@extends('layouts.app')

@section('title', 'Editar cliente')

@section('crumb')
    <span>Administração</span><span class="sep">/</span>
    <a href="{{ route('admin.clientes.index') }}" style="color:inherit;">Clientes</a><span class="sep">/</span><b>Editar</b>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.clientes.update', $cliente) }}" class="userform" style="max-width:520px;">
        @csrf @method('PUT')
        <div>
            <h2>Editar cliente</h2>
            <p class="userform__lead">Alterações valem para <strong>novos</strong> contratos; os já gerados mantêm o endereço da época.</p>
        </div>
        <div class="fields">
            <label>
                <span class="lbl">Nome</span>
                <input type="text" name="nome" value="{{ old('nome', $cliente->nome) }}" required>
            </label>
            <label>
                <span class="lbl">Endereço (uma linha por linha)</span>
                <textarea name="endereco" rows="4" required style="width:100%; resize:vertical;">{{ old('endereco', $cliente->endereco) }}</textarea>
            </label>
            <label>
                <span class="lbl">Ref. padrão do comprador (opcional)</span>
                <input type="text" name="ref_padrao" value="{{ old('ref_padrao', $cliente->ref_padrao) }}" placeholder="Ex.: CONTRACT NO. 26-003 DD. 17.02.2026">
            </label>
        </div>
        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn-coffee">Salvar</button>
            <a href="{{ route('admin.clientes.index') }}" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
@endsection
