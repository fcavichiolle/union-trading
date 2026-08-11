@extends('layouts.app')

@section('title', 'Corretoras & Brokers')
@section('subtitle', 'Corretoras da Union e brokers usados pelos clientes — alimentam os dropdowns da Tela NY. Fixações já registradas guardam o nome da época e não mudam.')

@section('crumb')
    <span>Administração</span><span class="sep">/</span><b>Corretoras</b>
@endsection

@section('content')
    <div class="admin-grid">
        <div>
            <div class="usercard" style="margin-bottom:22px;">
                <div class="utable__head" style="grid-template-columns: 1fr 160px;">
                    <span>Nossas corretoras</span><span class="r">Ações</span>
                </div>

                @forelse ($nossas as $c)
                    <div class="utable__row" style="grid-template-columns: 1fr 160px; position:static; align-items:center; gap:12px;">
                        <form method="POST" action="{{ route('admin.corretoras.update', $c) }}" id="c-{{ $c->id }}" style="margin:0;">
                            @csrf @method('PUT')
                            <input type="text" name="nome" value="{{ $c->nome }}" required style="height:38px; font-size:13.5px; width:100%;">
                        </form>
                        <div style="display:flex; gap:8px; justify-content:flex-end;">
                            <button type="submit" form="c-{{ $c->id }}" class="mini" title="Salvar alteração">Salvar</button>
                            <form method="POST" action="{{ route('admin.corretoras.destroy', $c) }}" onsubmit="return confirm('Remover esta corretora do cadastro? Fixações já registradas não são alteradas.');" style="margin:0;">
                                @csrf @method('DELETE')
                                <button type="submit" class="mini mini--danger">Excluir</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div style="padding:28px 22px; text-align:center; color:var(--muted);">Nenhuma corretora cadastrada — o formulário da Tela NY ficará sem opções.</div>
                @endforelse

                <div class="usercard__foot">
                    <span>{{ $nossas->count() }} {{ \Illuminate\Support\Str::plural('corretora', $nossas->count()) }}</span>
                </div>
            </div>

            <div class="usercard">
                <div class="utable__head" style="grid-template-columns: 1fr 160px;">
                    <span>Brokers dos clientes</span><span class="r">Ações</span>
                </div>

                @forelse ($doCliente as $c)
                    <div class="utable__row" style="grid-template-columns: 1fr 160px; position:static; align-items:center; gap:12px;">
                        <form method="POST" action="{{ route('admin.corretoras.update', $c) }}" id="c-{{ $c->id }}" style="margin:0;">
                            @csrf @method('PUT')
                            <input type="text" name="nome" value="{{ $c->nome }}" required style="height:38px; font-size:13.5px; width:100%;">
                        </form>
                        <div style="display:flex; gap:8px; justify-content:flex-end;">
                            <button type="submit" form="c-{{ $c->id }}" class="mini" title="Salvar alteração">Salvar</button>
                            <form method="POST" action="{{ route('admin.corretoras.destroy', $c) }}" onsubmit="return confirm('Remover este broker do cadastro? Fixações já registradas não são alteradas.');" style="margin:0;">
                                @csrf @method('DELETE')
                                <button type="submit" class="mini mini--danger">Excluir</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div style="padding:28px 22px; text-align:center; color:var(--muted);">Nenhum broker de cliente cadastrado (o campo é opcional na fixação).</div>
                @endforelse

                <div class="usercard__foot">
                    <span>{{ $doCliente->count() }} {{ \Illuminate\Support\Str::plural('broker', $doCliente->count()) }}</span>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.corretoras.store') }}" class="userform">
            @csrf
            <div>
                <h2>Adicionar corretora / broker</h2>
                <p class="userform__lead">Cada cliente usa um broker diferente — cadastre aqui e ele aparece na hora no formulário da Tela NY.</p>
            </div>
            <div class="fields">
                <label>
                    <span class="lbl">Nome</span>
                    <input type="text" name="nome" value="{{ old('nome') }}" placeholder="Ex.: Macquarie USA" required>
                    @error('nome') <div class="field-error">{{ $message }}</div> @enderror
                </label>
                <label>
                    <span class="lbl">Tipo</span>
                    <select name="tipo" required>
                        @foreach (\App\Models\Corretora::tipos() as $cod => $rotulo)
                            <option value="{{ $cod }}" @selected(old('tipo') === $cod)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                    @error('tipo') <div class="field-error">{{ $message }}</div> @enderror
                </label>
            </div>
            <button type="submit" class="btn-coffee" style="margin-top:2px;">Adicionar</button>
        </form>
    </div>
@endsection
