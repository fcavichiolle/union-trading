{{--
    O JavaScript do chat, numa função só (`UTChat.iniciar`) para servir a
    tela cheia e o chat flutuante. Incluído uma vez por página.

    REGRA DE SEGURANÇA que vale para tudo aqui: texto escrito por usuário
    entra na tela SEMPRE por `textContent` / `createTextNode`, nunca por
    innerHTML. Os únicos innerHTML são de marcação fixa (ícones).
--}}
@once
<script>
window.UTChat = (function () {
    'use strict';

    var REACOES = @json(\App\Models\Mensagem::REACOES);

    // Emojis do seletor do campo de texto, por grupo. Lista curta de
    // propósito: é um canal de trabalho, e o teclado do sistema (Win + .)
    // continua valendo para o resto.
    var EMOJIS = [
        ['Rostos', ['😀', '😁', '😂', '🤣', '😊', '🙂', '😉', '😍', '😎', '🤔', '😅', '😬', '😴', '😮', '😢', '😭', '😡', '🤯', '🥳', '🙄']],
        ['Gestos', ['👍', '👎', '👏', '🙏', '🤝', '💪', '👌', '✌️', '🤞', '👀', '🙌', '👋']],
        ['Trabalho', ['✅', '❌', '⚠️', '❗', '❓', '⏳', '📌', '📎', '📄', '📊', '📈', '📉', '💰', '📅', '📞', '✉️']],
        ['Café e logística', ['☕', '🌱', '🚚', '🚢', '⚓', '📦', '🏭', '🌧️', '☀️', '🔥', '🎉', '❤️']]
    ];

    var ICONE_REAGIR = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="13" r="8"></circle><path d="M8 14.5a3.5 3.5 0 0 0 6 0"></path><path d="M8.5 10.5h.01M13.5 10.5h.01"></path><path d="M19 2v6M16 5h6"></path></svg>';

    function el(tag, classe, texto) {
        var e = document.createElement(tag);
        if (classe) e.className = classe;
        if (texto !== undefined) e.textContent = texto;
        return e;
    }

    /**
     * @param raiz  o elemento [data-chat]
     * @param cfg   { usuarios: [...], carregar: bool, intervalo: ms, focar: bool, aoReceber: fn }
     */
    function iniciar(raiz, cfg) {
        cfg = cfg || {};

        var q = function (sel) { return raiz.querySelector(sel); };
        var lista = q('[data-chat-lista]'),
            corpo = q('[data-chat-corpo]'),
            form = q('[data-chat-form]'),
            campo = q('[data-chat-texto]'),
            enviar = q('[data-chat-enviar]'),
            anteriores = q('[data-chat-anteriores]'),
            btnAnteriores = q('[data-chat-btn-anteriores]'),
            caixaMencao = q('[data-chat-mencoes]'),
            btnEmoji = q('[data-chat-emoji-btn]'),
            painelEmoji = q('[data-chat-emoji-painel]'),
            token = document.querySelector('meta[name="csrf-token"]').content,
            urlBase = raiz.dataset.urlBase;

        var USUARIOS = cfg.usuarios || [];
        var ultimoId = 0, primeiroId = 0, buscando = false, relogio = null;

        function pedir(url, opcoes) {
            opcoes = opcoes || {};
            opcoes.headers = Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, opcoes.headers || {});
            return fetch(url, opcoes).then(function (r) { return r.ok ? r.json() : Promise.reject(r); });
        }

        function idsDaTela() {
            var linhas = lista.querySelectorAll('.msg');
            if (!linhas.length) { ultimoId = 0; primeiroId = 0; return; }
            primeiroId = parseInt(linhas[0].dataset.id, 10);
            ultimoId = parseInt(linhas[linhas.length - 1].dataset.id, 10);
        }

        function noFim() {
            // Só rola sozinho se o usuário já estava no fim — senão a tela
            // pula debaixo do dedo de quem está lendo o histórico.
            return corpo.scrollHeight - corpo.scrollTop - corpo.clientHeight < 80;
        }

        function rolarParaOFim() { corpo.scrollTop = corpo.scrollHeight; }

        /* ---------------- reações ---------------- */

        function chipDeReacao(r) {
            var b = el('button', 'reacao' + (r.minha ? ' is-minha' : ''));
            b.type = 'button';
            b.dataset.emoji = r.emoji;
            b.title = r.quem;
            b.appendChild(el('span', 'reacao__emoji', r.emoji));
            b.appendChild(el('span', 'reacao__total', String(r.total)));
            return b;
        }

        function botaoDeReagir() {
            var b = el('button', 'reacao reacao--mais');
            b.type = 'button';
            b.dataset.reagir = '';
            b.title = 'Reagir';
            b.setAttribute('aria-label', 'Reagir a esta mensagem');
            b.innerHTML = ICONE_REAGIR; // marcação fixa, sem dado de usuário
            return b;
        }

        /** Redesenha as reações de uma mensagem que está na tela. */
        function pintarReacoes(id, reacoes) {
            var linha = lista.querySelector('.msg[data-id="' + id + '"]');
            if (!linha) return;
            var caixa = linha.querySelector('[data-reacoes]');
            if (!caixa) return;

            // O seletor aberto mora dentro da caixa: preserva.
            var seletor = caixa.querySelector('.reacao-seletor');
            caixa.textContent = '';
            (reacoes || []).forEach(function (r) { caixa.appendChild(chipDeReacao(r)); });
            caixa.appendChild(botaoDeReagir());
            if (seletor) caixa.appendChild(seletor);
        }

        function fecharSeletorDeReacao() {
            var aberto = raiz.querySelector('.reacao-seletor');
            if (aberto) aberto.remove();
        }

        function abrirSeletorDeReacao(caixa) {
            var jaAberto = caixa.querySelector('.reacao-seletor');
            fecharSeletorDeReacao();
            if (jaAberto) return; // segundo clique no botão fecha

            var seletor = el('div', 'reacao-seletor');
            REACOES.forEach(function (emoji) {
                var b = el('button', 'reacao-seletor__item', emoji);
                b.type = 'button';
                b.dataset.reacao = emoji;
                b.title = 'Reagir com ' + emoji;
                seletor.appendChild(b);
            });
            caixa.appendChild(seletor);
            // Abre para baixo; na última mensagem, rola o que faltar.
            seletor.scrollIntoView({ block: 'nearest' });
        }

        function reagir(id, emoji) {
            pedir(urlBase + '/' + id + '/reacoes', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ emoji: emoji })
            }).then(function (dados) {
                pintarReacoes(dados.id, dados.reacoes);
            }).catch(function () { /* some no próximo ciclo de busca */ });
        }

        /* ---------------- mensagens ---------------- */

        /** Monta a linha da mensagem. Texto SEMPRE por textContent. */
        function montar(m) {
            var div = el('div', 'msg' + (m.minha ? ' msg--minha' : '') + (m.me_citou ? ' msg--citou' : ''));
            div.dataset.id = m.id;

            var bolha = el('div', 'msg__bolha'),
                topo = el('div', 'msg__topo');

            topo.appendChild(el('span', 'msg__autor', m.minha ? 'Você' : m.autor));
            topo.appendChild(el('span', 'msg__hora', m.hora));

            if (m.me_citou) {
                var marca = el('span', 'msg__citacao', 'citou você');
                marca.title = 'Você foi citado nesta mensagem';
                topo.appendChild(marca);
            }

            if (m.pode_apagar) topo.appendChild(formDeApagar(m.id));

            // Pedaços: menção ganha <span>, o TEXTO vai por textContent.
            var texto = el('p', 'msg__texto');
            (m.segmentos || []).forEach(function (s) {
                if (s.mencao) {
                    texto.appendChild(el('span', 'mencao' + (s.para_mim ? ' mencao--eu' : ''), s.texto));
                } else {
                    texto.appendChild(document.createTextNode(s.texto));
                }
            });

            var reacoes = el('div', 'msg__reacoes');
            reacoes.dataset.reacoes = '';
            (m.reacoes || []).forEach(function (r) { reacoes.appendChild(chipDeReacao(r)); });
            reacoes.appendChild(botaoDeReagir());

            bolha.appendChild(topo);
            bolha.appendChild(texto);
            bolha.appendChild(reacoes);
            div.appendChild(el('div', 'msg__avatar', m.iniciais));
            div.appendChild(bolha);

            return div;
        }

        function formDeApagar(id) {
            var f = el('form', 'msg__apagar');
            f.method = 'POST';
            f.action = urlBase + '/' + id;

            [['_token', token], ['_method', 'DELETE']].forEach(function (par) {
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = par[0]; i.value = par[1];
                f.appendChild(i);
            });

            var b = el('button', '', '×');
            b.type = 'submit'; b.title = 'Apagar mensagem';
            f.appendChild(b);

            return f;
        }

        function separadorDeDia(dia) {
            var div = el('div', 'chat__dia');
            div.dataset.dia = dia;
            div.appendChild(el('span', '', dia));
            return div;
        }

        function tirarVazio() {
            var vazio = lista.querySelector('[data-chat-vazio]');
            if (vazio) vazio.remove();
        }

        function mostrarVazio() {
            if (lista.querySelector('.msg') || lista.querySelector('[data-chat-vazio]')) return;
            var vazio = el('div', 'chat__vazio');
            vazio.dataset.chatVazio = '';
            vazio.appendChild(el('strong', '', 'Nenhuma mensagem ainda.'));
            vazio.appendChild(el('span', '', 'Escreva a primeira — ela aparece para toda a equipe.'));
            lista.appendChild(vazio);
        }

        function acrescentar(mensagens, noInicio) {
            if (!mensagens.length) return;

            tirarVazio();

            var fragmento = document.createDocumentFragment();
            var diasNaTela = Array.prototype.map.call(
                lista.querySelectorAll('.chat__dia'), function (d) { return d.dataset.dia; }
            );

            mensagens.forEach(function (m) {
                // A mesma mensagem pode chegar duas vezes (envio + busca).
                if (lista.querySelector('.msg[data-id="' + m.id + '"]')) return;
                if (diasNaTela.indexOf(m.dia) === -1) {
                    diasNaTela.push(m.dia);
                    fragmento.appendChild(separadorDeDia(m.dia));
                }
                fragmento.appendChild(montar(m));
            });

            if (noInicio) {
                var alturaAntes = corpo.scrollHeight;
                lista.insertBefore(fragmento, lista.firstChild);
                // Mantém a posição de leitura ao carregar histórico.
                corpo.scrollTop += corpo.scrollHeight - alturaAntes;
            } else {
                var estavaNoFim = noFim();
                lista.appendChild(fragmento);
                if (estavaNoFim) rolarParaOFim();
            }

            idsDaTela();
        }

        function buscarNovas() {
            if (buscando) return;
            buscando = true;

            pedir(raiz.dataset.urlNovas + '?depois=' + ultimoId + (primeiroId ? '&visiveis_de=' + primeiroId : ''))
                .then(function (dados) {
                    acrescentar(dados.mensagens || [], false);

                    // Reações do trecho aberto: quem não veio ficou sem nenhuma.
                    if (dados.reacoes) {
                        Array.prototype.forEach.call(lista.querySelectorAll('.msg'), function (linha) {
                            var id = linha.dataset.id,
                                tinha = linha.querySelector('.reacao[data-emoji]');
                            if (dados.reacoes[id] || tinha) pintarReacoes(id, dados.reacoes[id] || []);
                        });
                    }

                    if ((dados.mensagens || []).length && cfg.aoReceber) cfg.aoReceber();
                })
                .catch(function () { /* rede caiu: tenta no próximo ciclo */ })
                .finally(function () { buscando = false; });
        }

        function enviarMensagem() {
            var texto = campo.value.trim();
            if (!texto) return;

            enviar.disabled = true;

            pedir(raiz.dataset.urlEnviar, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ texto: texto })
            })
                .then(function (dados) {
                    campo.value = '';
                    campo.style.height = '';
                    acrescentar([dados.mensagem], false);
                    rolarParaOFim();
                })
                .catch(function () {
                    // Falhou pelo fetch: a mensagem ainda vai pelo POST normal.
                    form.submit();
                })
                .finally(function () { enviar.disabled = false; });
        }

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            enviarMensagem();
        });

        // Cliques dentro da lista: reações e apagar, por delegação (vale para
        // o que veio do servidor e para o que o JS montou depois).
        lista.addEventListener('click', function (ev) {
            var alvo = ev.target.closest('button');
            if (!alvo || !lista.contains(alvo)) return;

            var linha = alvo.closest('.msg');
            if (!linha) return;

            if (alvo.dataset.reacao !== undefined) {           // item do seletor
                reagir(linha.dataset.id, alvo.dataset.reacao);
                fecharSeletorDeReacao();
            } else if (alvo.dataset.reagir !== undefined) {    // botão "reagir"
                abrirSeletorDeReacao(alvo.closest('[data-reacoes]'));
            } else if (alvo.dataset.emoji !== undefined) {     // chip existente
                reagir(linha.dataset.id, alvo.dataset.emoji);
            }
        });

        lista.addEventListener('submit', function (ev) {
            var f = ev.target.closest('form.msg__apagar');
            if (!f) return;
            ev.preventDefault();
            if (!confirm('Apagar esta mensagem?')) return;

            var linha = f.closest('.msg');
            pedir(f.action, { method: 'DELETE' })
                .then(function () {
                    linha.remove();
                    idsDaTela();
                    mostrarVazio();
                })
                .catch(function () { f.submit(); });
        });

        /* ---------------- seletor de emojis do campo ---------------- */

        function inserirNoCampo(texto) {
            var ini = campo.selectionStart, fim = campo.selectionEnd;
            campo.value = campo.value.slice(0, ini) + texto + campo.value.slice(fim);
            var pos = ini + texto.length;
            campo.focus();
            campo.setSelectionRange(pos, pos);
        }

        function montarPainelDeEmojis() {
            if (painelEmoji.childElementCount) return;

            EMOJIS.forEach(function (grupo) {
                painelEmoji.appendChild(el('div', 'emoji-painel__titulo', grupo[0]));
                var grade = el('div', 'emoji-painel__grade');
                grupo[1].forEach(function (emoji) {
                    var b = el('button', 'emoji-painel__item', emoji);
                    b.type = 'button';
                    b.dataset.emoji = emoji;
                    grade.appendChild(b);
                });
                painelEmoji.appendChild(grade);
            });
        }

        function fecharEmojis() {
            painelEmoji.hidden = true;
            btnEmoji.setAttribute('aria-expanded', 'false');
        }

        btnEmoji.addEventListener('click', function () {
            if (!painelEmoji.hidden) { fecharEmojis(); return; }
            montarPainelDeEmojis();
            fecharMencao();
            painelEmoji.hidden = false;
            btnEmoji.setAttribute('aria-expanded', 'true');
        });

        // mousedown + preventDefault: o campo não perde o cursor ao escolher.
        painelEmoji.addEventListener('mousedown', function (ev) {
            ev.preventDefault();
            var b = ev.target.closest('.emoji-painel__item');
            if (b) inserirNoCampo(b.dataset.emoji);
        });

        // Clique fora fecha o seletor de emojis e o de reações.
        document.addEventListener('click', function (ev) {
            if (!painelEmoji.hidden && !painelEmoji.contains(ev.target) && !btnEmoji.contains(ev.target)) fecharEmojis();
            if (!ev.target.closest('[data-reacoes]')) fecharSeletorDeReacao();
        });

        /* ---------------- menção com @ ---------------- */

        var sugestoes = [], selecionada = -1, inicioDoArroba = -1;

        /** Texto entre o "@" mais próximo e o cursor, se houver. */
        function trechoDaMencao() {
            var pos = campo.selectionStart,
                antes = campo.value.slice(0, pos),
                arroba = antes.lastIndexOf('@');

            if (arroba === -1) return null;

            // O "@" tem de começar palavra (início do texto ou depois de espaço).
            var anterior = arroba === 0 ? ' ' : antes.charAt(arroba - 1);
            if (!/\s/.test(anterior)) return null;

            var digitado = antes.slice(arroba + 1);

            // Some depois de uma quebra de linha ou de nome longo demais.
            if (/[\n]/.test(digitado) || digitado.length > 40) return null;

            inicioDoArroba = arroba;

            return digitado;
        }

        function fecharMencao() {
            caixaMencao.hidden = true;
            caixaMencao.textContent = '';
            sugestoes = [];
            selecionada = -1;
        }

        function abrirMencao(digitado) {
            var busca = digitado.toLowerCase();

            sugestoes = USUARIOS.filter(function (u) {
                return busca === '' || u.nome.toLowerCase().indexOf(busca) > -1;
            }).slice(0, 6);

            if (!sugestoes.length) { fecharMencao(); return; }

            caixaMencao.textContent = '';
            selecionada = 0;

            sugestoes.forEach(function (u, i) {
                var item = el('button', 'mencao-item' + (i === 0 ? ' is-sel' : ''));
                item.type = 'button';
                item.appendChild(el('span', 'mencao-item__nome', u.nome));
                item.appendChild(el('span', 'mencao-item__perfil', u.perfil || ''));
                item.addEventListener('mousedown', function (ev) {
                    ev.preventDefault();
                    escolherMencao(i);
                });
                caixaMencao.appendChild(item);
            });

            caixaMencao.hidden = false;
        }

        function marcarSelecionada() {
            Array.prototype.forEach.call(caixaMencao.children, function (item, i) {
                item.classList.toggle('is-sel', i === selecionada);
            });
        }

        function escolherMencao(i) {
            var u = sugestoes[i];
            if (!u) return;

            var pos = campo.selectionStart,
                antes = campo.value.slice(0, inicioDoArroba),
                depois = campo.value.slice(pos);

            campo.value = antes + '@' + u.nome + ' ' + depois;

            var novaPos = (antes + '@' + u.nome + ' ').length;
            campo.setSelectionRange(novaPos, novaPos);
            campo.focus();

            fecharMencao();
        }

        campo.addEventListener('input', function () {
            var digitado = trechoDaMencao();
            if (digitado === null) { fecharMencao(); return; }
            abrirMencao(digitado);
        });

        campo.addEventListener('blur', function () { setTimeout(fecharMencao, 120); });

        // Enter envia, Shift+Enter pula linha (comportamento de chat).
        // Com a lista de menção aberta, as setas e o Enter são dela.
        campo.addEventListener('keydown', function (ev) {
            if (!caixaMencao.hidden && sugestoes.length) {
                if (ev.key === 'ArrowDown') {
                    ev.preventDefault();
                    selecionada = (selecionada + 1) % sugestoes.length;
                    marcarSelecionada();
                    return;
                }
                if (ev.key === 'ArrowUp') {
                    ev.preventDefault();
                    selecionada = (selecionada - 1 + sugestoes.length) % sugestoes.length;
                    marcarSelecionada();
                    return;
                }
                if (ev.key === 'Enter' || ev.key === 'Tab') {
                    ev.preventDefault();
                    escolherMencao(selecionada);
                    return;
                }
                if (ev.key === 'Escape') {
                    ev.preventDefault();
                    ev.stopPropagation(); // não fecha o chat flutuante junto
                    fecharMencao();
                    return;
                }
            }

            if (ev.key === 'Escape' && !painelEmoji.hidden) {
                ev.preventDefault();
                ev.stopPropagation();
                fecharEmojis();
                return;
            }

            if (ev.key === 'Enter' && !ev.shiftKey) {
                ev.preventDefault();
                enviarMensagem();
            }
        });

        // Cresce com o texto, até um limite.
        campo.addEventListener('input', function () {
            campo.style.height = 'auto';
            campo.style.height = Math.min(campo.scrollHeight, 160) + 'px';
        });

        if (btnAnteriores) {
            btnAnteriores.addEventListener('click', function () {
                btnAnteriores.disabled = true;

                pedir(raiz.dataset.urlNovas + '?antes=' + primeiroId)
                    .then(function (dados) {
                        acrescentar(dados.mensagens || [], true);
                        if (!dados.tem_anteriores) anteriores.hidden = true;
                    })
                    .finally(function () { btnAnteriores.disabled = false; });
            });
        }

        /* ---------------- ciclo de vida ---------------- */

        function ligar() {
            if (relogio) return;
            relogio = setInterval(buscarNovas, cfg.intervalo || 10000);
        }

        function desligar() {
            clearInterval(relogio);
            relogio = null;
        }

        /** Chat flutuante: busca as últimas mensagens ao abrir pela 1ª vez. */
        function carregarRecentes() {
            return pedir(raiz.dataset.urlNovas + '?recentes=1').then(function (dados) {
                USUARIOS = dados.usuarios || [];
                lista.textContent = '';
                acrescentar(dados.mensagens || [], false);
                mostrarVazio();
                if (anteriores) anteriores.hidden = !dados.tem_anteriores;
                rolarParaOFim();
            });
        }

        idsDaTela();

        if (cfg.carregar) {
            carregarRecentes().catch(function () {
                lista.textContent = '';
                lista.appendChild(el('div', 'chat__vazio', 'Não foi possível carregar as mensagens.'));
            });
        } else {
            // Abre na marca de "novas mensagens" quando existir; senão, no fim.
            var marca = lista.querySelector('[data-chat-marca-novas]');
            if (marca) marca.scrollIntoView({ block: 'center' });
            else rolarParaOFim();
        }

        if (cfg.focar !== false) campo.focus();
        ligar();

        return {
            ligar: function () { buscarNovas(); ligar(); },
            desligar: desligar,
            focar: function () { campo.focus(); rolarParaOFim(); }
        };
    }

    return { iniciar: iniciar };
})();
</script>
@endonce
