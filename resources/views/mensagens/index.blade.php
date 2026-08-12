@extends('layouts.app')

@section('title', 'Mensagens')
@section('subtitle', 'Canal da equipe. Todo mundo que usa o sistema lê e escreve aqui.')

@section('crumb')
    <b>Mensagens</b>
@endsection

@section('content')
    <div class="chat" id="chat"
         data-url-novas="{{ route('mensagens.novas') }}"
         data-url-enviar="{{ route('mensagens.store') }}">

        <div class="chat__corpo" id="chatCorpo">
            {{-- Botão de histórico: só aparece quando existe coisa mais antiga
                 do que as {{ \App\Models\Mensagem::POR_PAGINA }} carregadas. --}}
            <div class="chat__anteriores" id="chatAnteriores" @unless ($temAnteriores) hidden @endunless>
                <button type="button" class="btn btn-ghost" id="btnAnteriores">Carregar mensagens anteriores</button>
            </div>

            <div id="chatLista">
                @forelse ($mensagens as $i => $m)
                    {{-- Separador de dia, quando a data muda --}}
                    @if ($i === 0 || $mensagens[$i - 1]['dia'] !== $m['dia'])
                        <div class="chat__dia" data-dia="{{ $m['dia'] }}"><span>{{ $m['dia'] }}</span></div>
                    @endif

                    {{-- Linha "novas mensagens": marca onde o usuário parou --}}
                    @if ($naoLidas > 0 && $i === $mensagens->count() - $naoLidas)
                        <div class="chat__novas" id="marcaNovas"><span>novas mensagens</span></div>
                    @endif

                    <div class="msg {{ $m['minha'] ? 'msg--minha' : '' }} {{ $m['me_citou'] ? 'msg--citou' : '' }}" data-id="{{ $m['id'] }}">
                        <div class="msg__avatar">{{ $m['iniciais'] }}</div>
                        <div class="msg__bolha">
                            <div class="msg__topo">
                                <span class="msg__autor">{{ $m['minha'] ? 'Você' : $m['autor'] }}</span>
                                <span class="msg__hora">{{ $m['hora'] }}</span>
                                @if ($m['me_citou'])
                                    <span class="msg__citacao" title="Você foi citado nesta mensagem">citou você</span>
                                @endif
                                @if ($m['pode_apagar'])
                                    <form method="POST" action="{{ route('mensagens.destroy', $m['id']) }}"
                                          onsubmit="return confirm('Apagar esta mensagem?');" class="msg__apagar">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Apagar mensagem">×</button>
                                    </form>
                                @endif
                            </div>
                            {{-- Texto em pedaços: as menções ganham destaque, mas
                                 cada pedaço sai ESCAPADO (Blade aqui, textContent
                                 no JS). Nunca innerHTML com texto de usuário.

                                 Um <span> por pedaço, SEM @if no meio: duas
                                 diretivas colnadas (@endif@endforeach) não são
                                 compiladas pelo Blade, e separá-las com espaço
                                 meteria espaço dentro da mensagem (o texto usa
                                 white-space: pre-wrap). --}}
                            <p class="msg__texto">@foreach ($m['segmentos'] as $s)<span class="{{ $s['mencao'] ? 'mencao' . ($s['para_mim'] ? ' mencao--eu' : '') : '' }}">{{ $s['texto'] }}</span>@endforeach</p>
                        </div>
                    </div>
                @empty
                    <div class="chat__vazio" id="chatVazio">
                        <strong>Nenhuma mensagem ainda.</strong>
                        <span>Escreva a primeira — ela aparece para toda a equipe.</span>
                    </div>
                @endforelse
            </div>
        </div>

        <form class="chat__form" method="POST" action="{{ route('mensagens.store') }}" id="chatForm">
            @csrf
            <div class="chat__campo">
                {{-- Autocomplete de menção: aparece ao digitar "@". --}}
                <div class="mencao-lista" id="mencaoLista" hidden></div>
                <textarea name="texto" id="chatTexto" rows="1" maxlength="2000" required
                          placeholder="Escreva para a equipe… use @ para citar alguém (Enter envia, Shift+Enter pula linha)"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" id="chatEnviar">Enviar</button>
            @error('texto') <div class="field-error" style="flex-basis:100%;">{{ $message }}</div> @enderror
        </form>
    </div>

    {{-- Nota FORA do card, como nas telas de Estoque e Início: dentro dele
         ela ficava sem recuo lateral e a primeira letra era cortada pela
         borda (o card tem overflow: hidden). --}}
    <p class="chat__nota">
        A tela busca mensagens novas a cada 10 segundos. Cite alguém com <strong>@</strong> — a pessoa
        vê o aviso no menu até abrir o canal. As mensagens ficam <strong>criptografadas no banco</strong>;
        quem apaga é o autor (ou um administrador, e nesse caso fica registrado quem apagou, sem o texto).
    </p>

    <script>
        (function () {
            var chat = document.getElementById('chat'),
                lista = document.getElementById('chatLista'),
                corpo = document.getElementById('chatCorpo'),
                form = document.getElementById('chatForm'),
                campo = document.getElementById('chatTexto'),
                enviar = document.getElementById('chatEnviar'),
                anteriores = document.getElementById('chatAnteriores'),
                btnAnteriores = document.getElementById('btnAnteriores'),
                token = document.querySelector('meta[name="csrf-token"]').content;

            var ultimoId = 0, primeiroId = 0, buscando = false;

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

            /** Monta a linha da mensagem. Texto SEMPRE por textContent. */
            function montar(m) {
                var div = document.createElement('div');
                div.className = 'msg' + (m.minha ? ' msg--minha' : '') + (m.me_citou ? ' msg--citou' : '');
                div.dataset.id = m.id;

                var avatar = document.createElement('div');
                avatar.className = 'msg__avatar';
                avatar.textContent = m.iniciais;

                var bolha = document.createElement('div');
                bolha.className = 'msg__bolha';

                var topo = document.createElement('div');
                topo.className = 'msg__topo';

                var autor = document.createElement('span');
                autor.className = 'msg__autor';
                autor.textContent = m.minha ? 'Você' : m.autor;

                var hora = document.createElement('span');
                hora.className = 'msg__hora';
                hora.textContent = m.hora;

                topo.appendChild(autor);
                topo.appendChild(hora);

                if (m.me_citou) {
                    var marca = document.createElement('span');
                    marca.className = 'msg__citacao';
                    marca.title = 'Você foi citado nesta mensagem';
                    marca.textContent = 'citou você';
                    topo.appendChild(marca);
                }

                if (m.pode_apagar) topo.appendChild(formDeApagar(m.id));

                // Pedaços: menção ganha <span>, o TEXTO vai por textContent.
                // Nunca innerHTML — é conteúdo escrito por usuário.
                var texto = document.createElement('p');
                texto.className = 'msg__texto';

                (m.segmentos || []).forEach(function (s) {
                    if (s.mencao) {
                        var span = document.createElement('span');
                        span.className = 'mencao' + (s.para_mim ? ' mencao--eu' : '');
                        span.textContent = s.texto;
                        texto.appendChild(span);
                    } else {
                        texto.appendChild(document.createTextNode(s.texto));
                    }
                });

                bolha.appendChild(topo);
                bolha.appendChild(texto);
                div.appendChild(avatar);
                div.appendChild(bolha);

                return div;
            }

            function formDeApagar(id) {
                var f = document.createElement('form');
                f.method = 'POST';
                f.action = '{{ url('mensagens') }}/' + id;
                f.className = 'msg__apagar';
                f.addEventListener('submit', function (ev) {
                    if (!confirm('Apagar esta mensagem?')) ev.preventDefault();
                });

                [['_token', token], ['_method', 'DELETE']].forEach(function (par) {
                    var i = document.createElement('input');
                    i.type = 'hidden'; i.name = par[0]; i.value = par[1];
                    f.appendChild(i);
                });

                var b = document.createElement('button');
                b.type = 'submit'; b.title = 'Apagar mensagem'; b.textContent = '×';
                f.appendChild(b);

                return f;
            }

            function separadorDeDia(dia) {
                var div = document.createElement('div');
                div.className = 'chat__dia';
                div.dataset.dia = dia;
                var s = document.createElement('span');
                s.textContent = dia;
                div.appendChild(s);
                return div;
            }

            function acrescentar(mensagens, noInicio) {
                if (!mensagens.length) return;

                var vazio = document.getElementById('chatVazio');
                if (vazio) vazio.remove();

                var fragmento = document.createDocumentFragment();
                var diasNaTela = Array.prototype.map.call(
                    lista.querySelectorAll('.chat__dia'), function (d) { return d.dataset.dia; }
                );

                mensagens.forEach(function (m) {
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

                fetch(chat.dataset.urlNovas + '?depois=' + ultimoId, {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                    .then(function (dados) {
                        acrescentar(dados.mensagens || [], false);
                        // Chegou mensagem => o badge do menu já não vale.
                        if ((dados.mensagens || []).length) limparBadge();
                    })
                    .catch(function () { /* rede caiu: tenta no próximo ciclo */ })
                    .finally(function () { buscando = false; });
            }

            function limparBadge() {
                var badge = document.querySelector('.sb-link.is-active .sb-badge');
                if (badge) badge.remove();
            }

            function enviarMensagem() {
                var texto = campo.value.trim();
                if (!texto) return;

                enviar.disabled = true;

                fetch(chat.dataset.urlEnviar, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({ texto: texto })
                })
                    .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
                    .then(function (dados) {
                        campo.value = '';
                        campo.style.height = '';
                        acrescentar([dados.mensagem], false);
                        rolarParaOFim();
                    })
                    .catch(function () {
                        // Sem JS/fetch a mensagem ainda vai pelo POST normal.
                        form.submit();
                    })
                    .finally(function () { enviar.disabled = false; });
            }

            form.addEventListener('submit', function (ev) {
                ev.preventDefault();
                enviarMensagem();
            });

            /* ---------------- menção com @ ---------------- */

            var USUARIOS = @json($usuarios),
                caixaMencao = document.getElementById('mencaoLista'),
                sugestoes = [],
                selecionada = -1,
                inicioDoArroba = -1;

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
                    var item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'mencao-item' + (i === 0 ? ' is-sel' : '');
                    item.dataset.indice = i;

                    var nome = document.createElement('span');
                    nome.className = 'mencao-item__nome';
                    nome.textContent = u.nome;

                    var perfil = document.createElement('span');
                    perfil.className = 'mencao-item__perfil';
                    perfil.textContent = u.perfil || '';

                    item.appendChild(nome);
                    item.appendChild(perfil);
                    item.addEventListener('mousedown', function (ev) {
                        ev.preventDefault();
                        escolherMencao(i);
                    });

                    caixaMencao.appendChild(item);
                });

                caixaMencao.hidden = false;
            }

            function marcarSelecionada() {
                Array.prototype.forEach.call(caixaMencao.children, function (el, i) {
                    el.classList.toggle('is-sel', i === selecionada);
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
                        fecharMencao();
                        return;
                    }
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

                    fetch(chat.dataset.urlNovas + '?antes=' + primeiroId, {
                        headers: { 'Accept': 'application/json' }
                    })
                        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                        .then(function (dados) {
                            acrescentar(dados.mensagens || [], true);
                            if (!dados.tem_anteriores) anteriores.hidden = true;
                        })
                        .finally(function () { btnAnteriores.disabled = false; });
                });
            }

            idsDaTela();

            // Abre na marca de "novas mensagens" quando existir; senão, no fim.
            var marca = document.getElementById('marcaNovas');
            if (marca) marca.scrollIntoView({ block: 'center' });
            else rolarParaOFim();

            campo.focus();
            setInterval(buscarNovas, 10000);
        })();
    </script>
@endsection
