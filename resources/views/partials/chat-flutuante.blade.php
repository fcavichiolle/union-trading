{{--
    Chat flutuante: o canal da equipe num painel pequeno, em qualquer tela,
    para responder sem sair do que se está fazendo. Não aparece na própria
    tela de Mensagens (lá o chat já está aberto, em tamanho cheio).

    As mensagens só são buscadas quando o painel abre pela primeira vez.
    Fechado, ele só pergunta de tempo em tempo quantas há por ler.
--}}
@php $porLer = (int) ($badgesMenu['mensagens.index'] ?? 0); @endphp
<div class="chatf" id="chatFlutuante" data-url-resumo="{{ route('mensagens.resumo') }}">
    <div class="chatf__painel" id="chatfPainel" role="dialog" aria-label="Mensagens da equipe" hidden>
        <div class="chatf__topo">
            <strong>Mensagens</strong>
            <a href="{{ route('mensagens.index') }}" class="chatf__acao" title="Abrir em tela cheia" aria-label="Abrir mensagens em tela cheia">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6M21 3l-7 7M9 21H3v-6M3 21l7-7"></path></svg>
            </a>
            <button type="button" class="chatf__acao" id="chatfFechar" title="Fechar (Esc)" aria-label="Fechar mensagens">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>
            </button>
        </div>
        @include('mensagens._caixa', ['mini' => true, 'mensagens' => null])
    </div>

    <button type="button" class="chatf__botao" id="chatfBotao" aria-label="Abrir mensagens da equipe" aria-expanded="false" aria-controls="chatfPainel" title="Mensagens">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"></path></svg>
        <span class="chatf__badge" id="chatfBadge" @if ($porLer === 0) hidden @endif>{{ $porLer > 99 ? '99+' : $porLer }}</span>
    </button>
</div>

@include('mensagens._script')
<script>
    (function () {
        var caixa = document.getElementById('chatFlutuante'),
            painel = document.getElementById('chatfPainel'),
            botao = document.getElementById('chatfBotao'),
            fechar = document.getElementById('chatfFechar'),
            badge = document.getElementById('chatfBadge'),
            chat = null,            // criado só na primeira abertura
            CHAVE = 'utChatAberto'; // lembra aberto/fechado ao trocar de tela

        function lembrar(aberto) {
            try { sessionStorage.setItem(CHAVE, aberto ? '1' : '0'); } catch (e) { /* modo privado */ }
        }

        function estavaAberto() {
            try { return sessionStorage.getItem(CHAVE) === '1'; } catch (e) { return false; }
        }

        function pintarBadge(n) {
            badge.hidden = !n;
            badge.textContent = n > 99 ? '99+' : String(n);

            // O número do menu lateral acompanha, para os dois não divergirem.
            var doMenu = document.querySelector('a[href="{{ route('mensagens.index') }}"] .sb-badge');
            if (doMenu && !n) doMenu.remove();
        }

        function abrir(focar) {
            painel.hidden = false;
            botao.setAttribute('aria-expanded', 'true');
            caixa.classList.add('is-aberto');
            lembrar(true);
            pintarBadge(0); // abrir o chat marca o canal como lido

            if (!chat) {
                chat = UTChat.iniciar(painel.querySelector('[data-chat]'), { carregar: true, focar: focar });
            } else {
                chat.ligar();
                if (focar) chat.focar();
            }
        }

        function fecharPainel() {
            painel.hidden = true;
            botao.setAttribute('aria-expanded', 'false');
            caixa.classList.remove('is-aberto');
            lembrar(false);
            if (chat) chat.desligar();
        }

        botao.addEventListener('click', function () {
            if (painel.hidden) abrir(true); else fecharPainel();
        });
        fechar.addEventListener('click', function () { fecharPainel(); botao.focus(); });

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && !painel.hidden) { fecharPainel(); botao.focus(); }
        });

        // Fechado: só o contador, a cada 30 s. Aberto, quem busca é o chat.
        setInterval(function () {
            if (!painel.hidden || document.hidden) return;
            fetch(caixa.dataset.urlResumo, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (d) { pintarBadge(d.nao_lidas || 0); })
                .catch(function () { /* tenta de novo no próximo ciclo */ });
        }, 30000);

        // Estava aberto na tela anterior: reabre, sem roubar o foco do que a
        // pessoa veio fazer nesta tela.
        if (estavaAberto()) abrir(false);
    })();
</script>
