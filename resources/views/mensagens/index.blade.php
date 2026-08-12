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

                    <div class="msg {{ $m['minha'] ? 'msg--minha' : '' }}" data-id="{{ $m['id'] }}">
                        <div class="msg__avatar">{{ $m['iniciais'] }}</div>
                        <div class="msg__bolha">
                            <div class="msg__topo">
                                <span class="msg__autor">{{ $m['minha'] ? 'Você' : $m['autor'] }}</span>
                                <span class="msg__hora">{{ $m['hora'] }}</span>
                                @if ($m['pode_apagar'])
                                    <form method="POST" action="{{ route('mensagens.destroy', $m['id']) }}"
                                          onsubmit="return confirm('Apagar esta mensagem?');" class="msg__apagar">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Apagar mensagem">×</button>
                                    </form>
                                @endif
                            </div>
                            {{-- Texto do usuário: escapado pelo Blade, e o JS das
                                 mensagens novas usa textContent. Nunca innerHTML. --}}
                            <p class="msg__texto">{{ $m['texto'] }}</p>
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
            <textarea name="texto" id="chatTexto" rows="1" maxlength="2000" required
                      placeholder="Escreva para a equipe… (Enter envia, Shift+Enter pula linha)"></textarea>
            <button type="submit" class="btn btn-primary" id="chatEnviar">Enviar</button>
            @error('texto') <div class="field-error" style="flex-basis:100%;">{{ $message }}</div> @enderror
        </form>

        <p class="chat__nota">
            A tela busca mensagens novas a cada 10 segundos. Mensagem apagada sai para todos —
            quando um administrador apaga a mensagem de outra pessoa, o texto fica registrado no log de auditoria.
        </p>
    </div>

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
                div.className = 'msg' + (m.minha ? ' msg--minha' : '');
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

                if (m.pode_apagar) topo.appendChild(formDeApagar(m.id));

                var texto = document.createElement('p');
                texto.className = 'msg__texto';
                texto.textContent = m.texto;

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

            // Enter envia, Shift+Enter pula linha (comportamento de chat).
            campo.addEventListener('keydown', function (ev) {
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
