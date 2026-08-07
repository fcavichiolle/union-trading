@extends('layouts.app')

@section('title', 'Qualidades')
@section('subtitle', 'Descrições de café usadas no campo QUALITY dos contratos.')

@section('crumb')
    <span>Administração</span><span class="sep">/</span><b>Qualidades</b>
@endsection

@section('content')
    <div class="admin-grid">
        <div class="usercard">
            <div class="utable__head" style="grid-template-columns: 1fr 160px;">
                <span>Descrição</span><span class="r">Ações</span>
            </div>

            @forelse ($qualidades as $q)
                <div class="utable__row" style="grid-template-columns: 1fr 160px; position:static; align-items:center; gap:12px;">
                    <form method="POST" action="{{ route('admin.qualidades.update', $q) }}" id="q-{{ $q->id }}" style="margin:0;">
                        @csrf @method('PUT')
                        <input type="text" name="descricao" value="{{ $q->descricao }}" required style="height:38px; font-size:13.5px; width:100%;">
                    </form>
                    <div style="display:flex; gap:8px; justify-content:flex-end;">
                        <button type="submit" form="q-{{ $q->id }}" class="mini" title="Salvar alteração">Salvar</button>
                        <form method="POST" action="{{ route('admin.qualidades.destroy', $q) }}" onsubmit="return confirm('Remover esta qualidade?');" style="margin:0;">
                            @csrf @method('DELETE')
                            <button type="submit" class="mini mini--danger">Excluir</button>
                        </form>
                    </div>
                </div>
            @empty
                <div style="padding:28px 22px; text-align:center; color:var(--muted);">Nenhuma qualidade cadastrada ainda.</div>
            @endforelse

            <div class="usercard__foot">
                <span>{{ $qualidades->total() }} {{ \Illuminate\Support\Str::plural('qualidade', $qualidades->total()) }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.qualidades.store') }}" class="userform">
            @csrf
            <div>
                <h2>Adicionar qualidade</h2>
                <p class="userform__lead">Ex.: CAFÉ ARÁB NAT BRASIL CRIBA 14/16 SS GC</p>
            </div>
            <div class="fields">
                <label>
                    <span class="lbl">Descrição</span>
                    <input type="text" name="descricao" value="{{ old('descricao') }}" placeholder="CAFÉ ARÁB NAT BRASIL..." required>
                </label>
            </div>
            <button type="submit" class="btn-coffee" style="margin-top:2px;">Adicionar qualidade</button>
        </form>
    </div>

    @if ($qualidades->hasPages())
        <div class="pagination" style="margin-top:20px;">{{ $qualidades->links() }}</div>
    @endif
@endsection
