@extends('layouts.app')

@section('title', 'Transportadoras')
@section('subtitle', 'Transportadoras dos embarques. Os e-mails daqui são os destinatários da programação de estufagem, junto com os do armazém de saída.')

@section('crumb')
    <span>Cadastros</span><span class="sep">/</span><b>Transportadoras</b>
@endsection

@section('content')
    <div class="admin-grid">
        <div class="usercard">
            <div class="utable__head" style="grid-template-columns: 1fr;">
                <span>Transportadoras</span>
            </div>

            @forelse ($transportadoras as $t)
                {{-- Linha empilhada, como nas corretoras: a lista de e-mails é
                     longa demais para uma coluna. --}}
                <div class="utable__row" style="display:block; position:static; padding:16px 22px;">
                    <form method="POST" action="{{ route('admin.transportadoras.update', $t) }}" id="t-{{ $t->id }}" style="margin:0; display:grid; gap:10px;">
                        @csrf @method('PUT')
                        <input type="text" name="nome" value="{{ $t->nome }}" required
                               style="height:38px; font-size:13.5px; width:100%; font-weight:600;">
                        <label style="display:block;">
                            <span class="lbl">E-mails da programação</span>
                            <textarea name="emails" rows="2" placeholder="operacional@transportadora.com.br; monitoramento@transportadora.com.br"
                                      style="width:100%; resize:vertical; font-size:12.5px;">{{ $t->emails }}</textarea>
                        </label>
                    </form>

                    <form method="POST" action="{{ route('admin.transportadoras.destroy', $t) }}" id="del-t-{{ $t->id }}"
                          onsubmit="return confirm('Remover {{ $t->nome }} do cadastro? Embarques já lançados não são alterados.');" style="margin:0;">
                        @csrf @method('DELETE')
                    </form>

                    <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:10px;">
                        <button type="submit" form="t-{{ $t->id }}" class="mini" title="Salvar alteração">Salvar</button>
                        <button type="submit" form="del-t-{{ $t->id }}" class="mini mini--danger">Excluir</button>
                    </div>
                </div>
            @empty
                <div style="padding:28px 22px; text-align:center; color:var(--muted);">
                    Nenhuma transportadora cadastrada — o e-mail de programação sai só para o armazém.
                </div>
            @endforelse

            <div class="usercard__foot">
                <span>{{ $transportadoras->count() === 1 ? '1 transportadora' : $transportadoras->count() . ' transportadoras' }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.transportadoras.store') }}" class="userform">
            @csrf
            <div>
                <h2>Adicionar transportadora</h2>
                <p class="userform__lead">Use o mesmo nome que aparece nos embarques: é por ele que o sistema acha os e-mails.</p>
            </div>
            <div class="fields">
                <label>
                    <span class="lbl">Nome</span>
                    <input type="text" name="nome" value="{{ old('nome') }}" placeholder="Ex.: TRANSNORTE" required>
                    @error('nome') <div class="field-error">{{ $message }}</div> @enderror
                </label>
                <label>
                    <span class="lbl">E-mails da programação (opcional)</span>
                    <textarea name="emails" rows="3" placeholder="Separe vários por ponto e vírgula (;)" style="width:100%; resize:vertical;">{{ old('emails') }}</textarea>
                    @error('emails') <div class="field-error">{{ $message }}</div> @enderror
                </label>
            </div>
            <button type="submit" class="btn-coffee" style="margin-top:2px;">Adicionar</button>
        </form>
    </div>
@endsection
