@extends('layouts.app')

@section('title', 'Novo contrato')
@section('subtitle', 'Preencha os dados; o número de sacas, lotes e containers é calculado automaticamente.')

@section('crumb')
    <span>Contratos</span><span class="sep">/</span><b>Novo</b>
@endsection

@section('content')
<form method="POST" action="{{ route('contratos.store') }}" id="contratoForm" novalidate>
    @csrf

    @include('contratos._form', ['contrato' => null])

    <div style="display:flex; gap:14px; margin-top:22px;">
        <button type="submit" class="btn-coffee">
            <span>Gerar contrato</span>
            <span class="bean"><svg viewBox="0 0 24 32" width="15" height="20"><ellipse cx="12" cy="16" rx="10.5" ry="15" fill="#E9E2D1"></ellipse><path d="M12 2.5c3.2 6 3.2 21.5 0 27" stroke="#0A3A22" stroke-width="2.2" fill="none"></path></svg></span>
        </button>
        <button type="reset" class="btn btn-ghost">Limpar formulário</button>
    </div>
</form>
@endsection
