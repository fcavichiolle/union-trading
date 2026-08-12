@extends('layouts.app')

@section('title', 'Armazéns')
@section('subtitle', 'Onde o café entra. Alimentam o armazém previsto da compra e o armazém de cada entrega — e é por eles que o Estoque agrupa.')

@section('crumb')
    <span>Cadastros</span><span class="sep">/</span><b>Armazéns</b>
@endsection

@section('content')
    @error('armazem') <div class="alert alert-error">{{ $message }}</div> @enderror

    <div class="admin-grid">
        <div class="usercard">
            <div class="utable__head" style="grid-template-columns: 1.4fr 1fr 1.6fr 1fr 150px;">
                <span>Nome</span><span>Cidade / UF</span><span>Endereço</span><span>CNPJ</span><span class="r">Ações</span>
            </div>

            @forelse ($armazens as $a)
                <div class="utable__row" style="grid-template-columns: 1.4fr 1fr 1.6fr 1fr 150px; position:static; align-items:center; gap:10px;">
                    {{-- Edição na própria linha, como no cadastro de corretoras. --}}
                    <form method="POST" action="{{ route('admin.armazens.update', $a) }}" id="a-{{ $a->id }}" style="display:contents;">
                        @csrf @method('PUT')
                        <input type="text" name="nome" value="{{ $a->nome }}" required
                               style="height:38px; font-size:13.5px; width:100%;">
                        <div style="display:flex; gap:6px;">
                            <input type="text" name="cidade" value="{{ $a->cidade }}" required
                                   style="height:38px; font-size:13.5px; min-width:0;">
                            <select name="estado" required style="height:38px; font-size:13.5px; width:74px; flex:none;">
                                @foreach (\App\Models\Armazem::estados() as $uf)
                                    <option value="{{ $uf }}" @selected($a->estado === $uf)>{{ $uf }}</option>
                                @endforeach
                            </select>
                        </div>
                        <input type="text" name="endereco" value="{{ $a->endereco }}" placeholder="opcional"
                               style="height:38px; font-size:13.5px; width:100%;">
                        <input type="text" name="documento" value="{{ $a->documentoFormatado() }}" placeholder="opcional"
                               style="height:38px; font-size:13.5px; width:100%;">
                    </form>
                    <div style="display:flex; gap:8px; justify-content:flex-end;">
                        <button type="submit" form="a-{{ $a->id }}" class="mini" title="Salvar alteração">Salvar</button>
                        @if ($a->entregas_count || $a->compras_count)
                            {{-- Em uso não sai: a entrega aponta para o cadastro. --}}
                            <button type="button" class="mini" disabled
                                    title="{{ $a->entregas_count }} entrega(s) e {{ $a->compras_count }} compra(s) usam este armazém">Em uso</button>
                        @else
                            <form method="POST" action="{{ route('admin.armazens.destroy', $a) }}"
                                  onsubmit="return confirm('Remover {{ $a->nome }} do cadastro?');" style="margin:0;">
                                @csrf @method('DELETE')
                                <button type="submit" class="mini mini--danger">Excluir</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div style="padding:28px 22px; text-align:center; color:var(--muted);">
                    Nenhum armazém cadastrado — sem eles não é possível lançar entregas.
                </div>
            @endforelse

            <div class="usercard__foot">
                <span>{{ $armazens->count() }} {{ \Illuminate\Support\Str::plural('armazém', $armazens->count()) }}</span>
                <span class="mono">o Estoque agrupa por estes cadastros</span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.armazens.store') }}" class="userform">
            @csrf
            <div>
                <h2>Adicionar armazém</h2>
                <p class="userform__lead">
                    Assim que cadastrado, ele já aparece nos dropdowns da compra, da entrega e dos filtros de Estoque.
                </p>
            </div>
            <div class="fields">
                <label>
                    <span class="lbl">Nome do armazém</span>
                    <input type="text" name="nome" value="{{ old('nome') }}" placeholder="Ex.: ARMAZÉM SÃO JUDAS" required>
                    @error('nome') <div class="field-error">{{ $message }}</div> @enderror
                </label>
                <label>
                    <span class="lbl">Cidade</span>
                    <input type="text" name="cidade" value="{{ old('cidade') }}" placeholder="Ex.: Varginha" required>
                    @error('cidade') <div class="field-error">{{ $message }}</div> @enderror
                </label>
                <label>
                    <span class="lbl">Estado</span>
                    <select name="estado" required>
                        @foreach (\App\Models\Armazem::estados() as $uf)
                            <option value="{{ $uf }}" @selected(old('estado', 'MG') === $uf)>{{ $uf }}</option>
                        @endforeach
                    </select>
                    @error('estado') <div class="field-error">{{ $message }}</div> @enderror
                </label>
                <label>
                    <span class="lbl">Endereço <span class="hint">(opcional)</span></span>
                    <input type="text" name="endereco" value="{{ old('endereco') }}" placeholder="Rua, número, bairro">
                    @error('endereco') <div class="field-error">{{ $message }}</div> @enderror
                </label>
                <label>
                    <span class="lbl">CNPJ <span class="hint">(opcional)</span></span>
                    <input type="text" name="documento" value="{{ old('documento') }}" placeholder="00.000.000/0000-00">
                    @error('documento') <div class="field-error">{{ $message }}</div> @enderror
                </label>
            </div>
            <button type="submit" class="btn-coffee" style="margin-top:2px;">Adicionar</button>
        </form>
    </div>
@endsection
