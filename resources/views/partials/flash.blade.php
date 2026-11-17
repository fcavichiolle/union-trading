@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if (session('linkGerado'))
    <div class="alert alert-success link-share">
        <span class="link-share__lbl">Link temporário (válido por 7 dias):</span>
        <code class="link-share__url" id="linkCompartilhavel">{{ session('linkGerado') }}</code>
        <button type="button" class="btn btn-ghost js-copiar-link" data-alvo="linkCompartilhavel">Copiar</button>
    </div>
@endif

@if (session('aa'))
    {{-- Instruções de AA montadas pela fixação que acabou de ser registrada.
         Uma por cliente: o e-mail é sempre "Union X um cliente", então uma
         fixação em grupo com clientes diferentes gera mais de um botão. --}}
    <div class="alert alert-success aa-flash">
        <span class="aa-flash__lbl">
            {{ count(session('aa')) === 1 ? 'Instrução de AA pronta para enviar:' : 'Instruções de AA prontas para enviar:' }}
        </span>

        @foreach (session('aa') as $i => $aa)
            <div class="aa-flash__item">
                <div class="aa-flash__acoes">
                    @if ($aa['enviavel'])
                        <a href="{{ $aa['mailto'] }}" class="btn btn-ghost aa-flash__btn">
                            ✉ Abrir no Outlook — {{ $aa['cliente'] }}
                        </a>
                    @else
                        <span class="aa-flash__btn aa-flash__btn--off">✉ {{ $aa['cliente'] }}</span>
                    @endif

                    <button type="button" class="btn btn-ghost js-copiar-link" data-alvo="aa-corpo-{{ $i }}">
                        Copiar texto
                    </button>

                    {{-- Some da tela depois de enviado. Nasce `hidden` e só
                         aparece com JS: sem JS ele não faria nada, e o aviso
                         já desaparece sozinho na próxima página (é flash). --}}
                    <button type="button" class="btn btn-ghost js-aa-enviada" hidden>
                        ✓ Instrução enviada
                    </button>
                </div>

                {{-- Fonte do "Copiar texto". Fica escondido, mas é texto
                     escapado pelo Blade — nunca HTML montado no JS. --}}
                <pre id="aa-corpo-{{ $i }}" class="aa-flash__corpo" hidden>{{ $aa['corpo'] }}</pre>

                @foreach ($aa['avisos'] as $aviso)
                    <div class="aa-flash__aviso">⚠ {{ $aviso }}</div>
                @endforeach
            </div>
        @endforeach

        {{-- O Outlook resolve os endereços do `mailto:` preguiçosamente: os
             últimos da lista chegam como texto, e sem saber do atalho a pessoa
             clica um a um. A dica fica na tela porque o link não tem como
             chamar o `Recipients.ResolveAll` que a macro da planilha usava. --}}
        <p class="aa-flash__dica">
            No Outlook, <kbd>Ctrl</kbd>+<kbd>K</kbd> ("Verificar Nomes") valida todos
            os destinatários de uma vez.
        </p>
    </div>
@endif

@if (session('linkGerado') || session('aa'))
    <script>
        (function () {
            var botoes = document.querySelectorAll('.js-copiar-link');

            function selecionarEcopiar(texto) {
                // Fallback para navegador sem clipboard API (ou fora de
                // contexto seguro): usa uma textarea temporária.
                var ta = document.createElement('textarea');
                ta.value = texto;
                ta.setAttribute('readonly', '');
                ta.style.position = 'absolute';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                var ok = false;
                try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
                document.body.removeChild(ta);
                return ok;
            }

            // "Instrução enviada": tira o item da tela; quando some o
            // último, o aviso inteiro sai junto.
            document.querySelectorAll('.js-aa-enviada').forEach(function (btn) {
                btn.hidden = false;
                btn.addEventListener('click', function () {
                    var item = btn.closest('.aa-flash__item');
                    var caixa = btn.closest('.aa-flash');
                    if (item) item.remove();
                    if (caixa && !caixa.querySelector('.aa-flash__item')) caixa.remove();
                });
            });

            botoes.forEach(function (btn) {
                var original = btn.textContent.trim();

                function avisar(texto) {
                    btn.textContent = texto;
                    btn.disabled = true;
                    setTimeout(function () { btn.textContent = original; btn.disabled = false; }, 1800);
                }

                btn.addEventListener('click', function () {
                    var alvo = document.getElementById(btn.getAttribute('data-alvo'));
                    if (!alvo) return;
                    var texto = alvo.textContent.trim();

                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(texto)
                            .then(function () { avisar('Copiado!'); })
                            .catch(function () { avisar(selecionarEcopiar(texto) ? 'Copiado!' : 'Copie manualmente'); });
                    } else {
                        avisar(selecionarEcopiar(texto) ? 'Copiado!' : 'Copie manualmente');
                    }
                });
            });
        })();
    </script>
@endif

@if ($errors->any())
    @php $qtd = $errors->count(); @endphp
    <div class="alert alert-error">
        <strong>
            Não foi possível salvar —
            {{ $qtd === 1 ? '1 campo precisa de atenção' : $qtd . ' campos precisam de atenção' }}:
        </strong>
        <ul style="margin:6px 0 0; padding-left:18px;">
            @foreach ($errors->all() as $erro)
                <li>{{ $erro }}</li>
            @endforeach
        </ul>
        <span class="alert__hint">Os campos com problema estão destacados em vermelho no formulário abaixo.</span>
    </div>
@endif
