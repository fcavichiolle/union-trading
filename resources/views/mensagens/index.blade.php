@extends('layouts.app')

@section('title', 'Mensagens')
@section('subtitle', 'Canal da equipe. Todo mundo que usa o sistema lê e escreve aqui.')

@section('crumb')
    <b>Mensagens</b>
@endsection

@section('content')
    {{-- A caixa e o JavaScript são os mesmos do chat flutuante das outras
         telas (`_caixa` e `_script`); aqui as mensagens já vêm do servidor. --}}
    @include('mensagens._caixa', ['mini' => false, 'mensagens' => $mensagens, 'naoLidas' => $naoLidas, 'temAnteriores' => $temAnteriores])

    {{-- Nota FORA do card, como nas telas de Estoque e Início: dentro dele
         ela ficava sem recuo lateral e a primeira letra era cortada pela
         borda (o card tem overflow: hidden). --}}
    <p class="chat__nota">
        A tela busca mensagens novas a cada 10 segundos. Cite alguém com <strong>@</strong> — a pessoa
        vê o aviso no menu até abrir o canal. Passe o mouse numa mensagem para <strong>reagir</strong>; o botão
        ao lado do Enviar abre os <strong>emojis</strong>. As mensagens ficam <strong>criptografadas no banco</strong>;
        quem apaga é o autor (ou um administrador, e nesse caso fica registrado quem apagou, sem o texto).
    </p>

    @include('mensagens._script')
    <script>
        UTChat.iniciar(document.querySelector('[data-chat]'), {
            usuarios: @json($usuarios),
            // Chegou mensagem com a tela aberta => o badge do menu já não vale.
            aoReceber: function () {
                var badge = document.querySelector('.sb-link.is-active .sb-badge');
                if (badge) badge.remove();
            }
        });
    </script>
@endsection
