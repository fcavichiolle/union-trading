@extends('layouts.app')

@section('title', 'Clientes')
@section('subtitle', 'Compradores (BUYER) usados nos contratos de exportação.')

@section('crumb')
    <span>Administração</span><span class="sep">/</span><b>Clientes</b>
@endsection

@section('content')
    <div class="admin-grid">
        <div class="usercard">
            <div class="utable__head" style="grid-template-columns: 1fr 1.4fr 150px;">
                <span>Nome</span><span>Endereço</span><span class="r">Ações</span>
            </div>

            @forelse ($clientes as $cliente)
                <div class="utable__row" style="grid-template-columns: 1fr 1.4fr 150px; position:static;">
                    <span class="utable__name">{{ $cliente->nome }}</span>
                    <span style="font-size:12.5px; color:var(--muted); white-space:pre-line; line-height:1.45;">{{ $cliente->endereco }}</span>
                    <span style="justify-self:end; display:flex; gap:6px;">
                        <a href="{{ route('admin.clientes.edit', $cliente) }}" class="mini">Editar</a>
                        <form method="POST" action="{{ route('admin.clientes.destroy', $cliente) }}" onsubmit="return confirm('Remover o cliente {{ $cliente->nome }}?');" style="margin:0;">
                            @csrf @method('DELETE')
                            <button type="submit" class="mini mini--danger">Excluir</button>
                        </form>
                    </span>
                </div>
            @empty
                <div style="padding:28px 22px; text-align:center; color:var(--muted);">Nenhum cliente cadastrado ainda.</div>
            @endforelse

            <div class="usercard__foot">
                <span>{{ $clientes->total() }} {{ \Illuminate\Support\Str::plural('cliente', $clientes->total()) }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.clientes.store') }}" class="userform">
            @csrf
            <div>
                <h2>Adicionar cliente</h2>
                <p class="userform__lead">O nome vira a 1ª linha (em negrito) e o endereço o bloco abaixo, no campo BUYER do contrato.</p>
            </div>
            <div class="fields">
                <label>
                    <span class="lbl">Nome</span>
                    <input type="text" name="nome" value="{{ old('nome') }}" placeholder="Ex.: ICONA" required>
                </label>
                <label>
                    <span class="lbl">Endereço (uma linha por linha)</span>
                    <textarea name="endereco" rows="4" placeholder="INICIATIVAS COMERCIALES NAVARRAS S.A&#10;Principe de Vergara, 136 – of 9 y 10&#10;Madrid – Spain Zipcode 28002" required style="width:100%; resize:vertical;">{{ old('endereco') }}</textarea>
                </label>
                <label>
                    <span class="lbl">Ref. padrão do comprador (opcional)</span>
                    <input type="text" name="ref_padrao" value="{{ old('ref_padrao') }}" placeholder="Ex.: CONTRACT NO. 26-003 DD. 17.02.2026">
                    <span class="hint">Se preenchido, é lançado automaticamente no "Ref. Comprador" ao escolher este cliente.</span>
                </label>
            </div>
            <button type="submit" class="btn-coffee" style="margin-top:2px;">Adicionar cliente</button>
        </form>
    </div>

    @if ($clientes->hasPages())
        <div class="pagination" style="margin-top:20px;">{{ $clientes->links() }}</div>
    @endif
@endsection
