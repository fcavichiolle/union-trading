{{--
    A caixa do chat: lista de mensagens + campo de envio. Usada em dois
    lugares — a tela cheia (Mensagens) e o chat flutuante das outras telas.

    Parâmetros:
      $mini       true no chat flutuante (mais compacto, sem dica longa)
      $mensagens  as mensagens já prontas (`paraTela`) ou null: no flutuante
                  elas são buscadas só quando a pessoa abre o chat
      $naoLidas, $temAnteriores  só com $mensagens

    Os elementos são achados pelo JS por atributos `data-chat-*`, não por
    id: assim a mesma caixa pode existir duas vezes sem colidir.
--}}
@php
    $mini = $mini ?? false;
    $mensagens = $mensagens ?? null;
    $naoLidas = $naoLidas ?? 0;
@endphp
<div class="chat {{ $mini ? 'chat--mini' : '' }}" data-chat
     data-url-novas="{{ route('mensagens.novas') }}"
     data-url-enviar="{{ route('mensagens.store') }}"
     data-url-base="{{ url('mensagens') }}">

    <div class="chat__corpo" data-chat-corpo>
        {{-- Botão de histórico: só aparece quando existe coisa mais antiga
             do que as {{ \App\Models\Mensagem::POR_PAGINA }} carregadas. --}}
        <div class="chat__anteriores" data-chat-anteriores @unless ($temAnteriores ?? false) hidden @endunless>
            <button type="button" class="btn btn-ghost" data-chat-btn-anteriores>Carregar mensagens anteriores</button>
        </div>

        <div data-chat-lista>
            @if ($mensagens === null)
                <div class="chat__vazio" data-chat-vazio><span>Carregando…</span></div>
            @else
                @forelse ($mensagens as $i => $m)
                    {{-- Separador de dia, quando a data muda --}}
                    @if ($i === 0 || $mensagens[$i - 1]['dia'] !== $m['dia'])
                        <div class="chat__dia" data-dia="{{ $m['dia'] }}"><span>{{ $m['dia'] }}</span></div>
                    @endif

                    {{-- Linha "novas mensagens": marca onde o usuário parou --}}
                    @if ($naoLidas > 0 && $i === $mensagens->count() - $naoLidas)
                        <div class="chat__novas" data-chat-marca-novas><span>novas mensagens</span></div>
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
                                    {{-- O JS intercepta (confirma e apaga sem
                                         recarregar); sem JS, o POST normal. --}}
                                    <form method="POST" action="{{ route('mensagens.destroy', $m['id']) }}" class="msg__apagar">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Apagar mensagem">×</button>
                                    </form>
                                @endif
                            </div>
                            {{-- Texto em pedaços: as menções ganham destaque, mas
                                 cada pedaço sai ESCAPADO (Blade aqui, textContent
                                 no JS). Nunca innerHTML com texto de usuário.

                                 Um <span> por pedaço, SEM @if no meio: duas
                                 diretivas coladas (@endif@endforeach) não são
                                 compiladas pelo Blade, e separá-las com espaço
                                 meteria espaço dentro da mensagem (o texto usa
                                 white-space: pre-wrap). --}}
                            <p class="msg__texto">@foreach ($m['segmentos'] as $s)<span class="{{ $s['mencao'] ? 'mencao' . ($s['para_mim'] ? ' mencao--eu' : '') : '' }}">{{ $s['texto'] }}</span>@endforeach</p>

                            {{-- Reações: um botão por emoji usado (clicar liga/
                                 desliga a minha) e o botão de reagir. --}}
                            <div class="msg__reacoes" data-reacoes>
                                @foreach ($m['reacoes'] as $r)
                                    <button type="button" class="reacao {{ $r['minha'] ? 'is-minha' : '' }}" data-emoji="{{ $r['emoji'] }}" title="{{ $r['quem'] }}">
                                        <span class="reacao__emoji">{{ $r['emoji'] }}</span><span class="reacao__total">{{ $r['total'] }}</span>
                                    </button>
                                @endforeach
                                <button type="button" class="reacao reacao--mais" data-reagir title="Reagir" aria-label="Reagir a esta mensagem">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="13" r="8"></circle><path d="M8 14.5a3.5 3.5 0 0 0 6 0"></path><path d="M8.5 10.5h.01M13.5 10.5h.01"></path><path d="M19 2v6M16 5h6"></path></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="chat__vazio" data-chat-vazio>
                        <strong>Nenhuma mensagem ainda.</strong>
                        <span>Escreva a primeira — ela aparece para toda a equipe.</span>
                    </div>
                @endforelse
            @endif
        </div>
    </div>

    <form class="chat__form" method="POST" action="{{ route('mensagens.store') }}" data-chat-form>
        @csrf
        <div class="chat__campo">
            {{-- Autocomplete de menção: aparece ao digitar "@". --}}
            <div class="mencao-lista" data-chat-mencoes hidden></div>
            {{-- Seletor de emojis: abre acima do campo. --}}
            <div class="emoji-painel" data-chat-emoji-painel hidden></div>
            <textarea name="texto" data-chat-texto rows="1" maxlength="2000" required
                      placeholder="{{ $mini ? 'Escreva para a equipe…' : 'Escreva para a equipe… use @ para citar alguém (Enter envia, Shift+Enter pula linha)' }}"></textarea>
            <button type="button" class="chat__emoji" data-chat-emoji-btn title="Emojis" aria-label="Abrir seletor de emojis" aria-expanded="false">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M8.5 14.5a4 4 0 0 0 7 0"></path><path d="M9 9.5h.01M15 9.5h.01"></path></svg>
            </button>
        </div>
        <button type="submit" class="btn btn-primary" data-chat-enviar>Enviar</button>
        @error('texto') <div class="field-error" style="flex-basis:100%;">{{ $message }}</div> @enderror
    </form>
</div>
