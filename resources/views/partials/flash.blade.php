@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if (session('linkGerado'))
    <div class="alert alert-success link-share">
        <span class="link-share__lbl">Link temporário (válido por 7 dias):</span>
        <code class="link-share__url" id="linkCompartilhavel">{{ session('linkGerado') }}</code>
        <button type="button" class="btn btn-ghost js-copiar-link" data-alvo="linkCompartilhavel">Copiar</button>
    </div>

    <script>
        (function () {
            var btn = document.querySelector('.js-copiar-link');
            if (!btn) return;

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

            function avisar(texto) {
                var original = 'Copiar';
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
