@extends('layouts.app')

@section('title', 'Editar UT ' . (preg_replace('/\D+/', '', $contrato->numero_ut) ?: $contrato->numero_ut))
@section('subtitle', 'Alterações recalculam sacas, lotes e containers. O cliente e a qualidade são regravados no contrato ao salvar.')

@section('crumb')
    <span>Contratos</span><span class="sep">/</span>
    <a href="{{ route('contratos.show', $contrato) }}" style="color:inherit;">UT {{ preg_replace('/\D+/', '', $contrato->numero_ut) }}</a>
    <span class="sep">/</span><b>Editar</b>
@endsection

@section('content')
<form method="POST" action="{{ route('contratos.update', $contrato) }}" id="contratoForm" novalidate>
    @csrf
    @method('PUT')

    @include('contratos._form')

    <div style="display:flex; gap:14px; margin-top:22px; align-items:center;">
        <button type="submit" class="btn-coffee">
            <span>Salvar alterações</span>
            <span class="bean"><svg viewBox="0 0 24 32" width="15" height="20"><ellipse cx="12" cy="16" rx="10.5" ry="15" fill="#E9E2D1"></ellipse><path d="M12 2.5c3.2 6 3.2 21.5 0 27" stroke="#0A3A22" stroke-width="2.2" fill="none"></path></svg></span>
        </button>
        <a href="{{ route('contratos.show', $contrato) }}" class="btn btn-ghost">Cancelar edição</a>
    </div>
</form>
@endsection
