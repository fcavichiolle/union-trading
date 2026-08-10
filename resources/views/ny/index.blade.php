@extends('layouts.app')

@section('title', 'Tela NY — fixações')
@section('crumb')
    <span>Mercado</span><span class="sep">/</span><b>Tela NY</b>
@endsection
@section('subtitle')
    Fixação de preço dos contratos de exportação, por lotes. Marque vários contratos da
    mesma bolsa para fixar todos de uma vez contra a mesma tela. O preço de cada tranche
    (level + diferencial) e a virada para FIXED são calculados no servidor.
@endsection

@section('content')
    {{-- Régua de cotações (referência rápida; alimentada por /api/market) --}}
    <div class="mkt-strip" id="mktStrip" hidden>
        <span class="mkt-chip"><span class="mkt-lbl" data-mkt-lbl="kc"></span> <span class="mkt-val" data-mkt="kc"></span> <span class="mkt-dif" data-mkt-dif="kc"></span></span>
        <span class="mkt-chip"><span class="mkt-lbl" data-mkt-lbl="rc"></span> <span class="mkt-val" data-mkt="rc"></span> <span class="mkt-dif" data-mkt-dif="rc"></span></span>
        <span class="mkt-chip"><span class="mkt-lbl">Dólar</span> <span class="mkt-val" data-mkt="usd"></span> <span class="mkt-dif" data-mkt-dif="usd"></span></span>
        <a href="{{ route('mercado.index') }}" class="mkt-more">Ver todas as cotações →</a>
    </div>

    @if (count($posicao))
        <div class="card" style="margin-bottom:22px;">
            <div class="card__header card__header--dark mesh-texture">
                <h2>Posição de fixações por tela</h2>
            </div>
            <div class="card__body" style="padding:0;">
                <div class="table-wrap" style="border:0; border-radius:0;">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Tela</th>
                                <th>Bolsa</th>
                                <th class="num">A fixar (lotes)</th>
                                <th class="num">A fixar (sacas)</th>
                                <th class="num">Fixado (lotes)</th>
                                <th class="num">Level médio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($posicao as $p)
                                <tr>
                                    <td><strong>{{ $p['tela'] === 'SEM_TELA' ? 'Sem tela definida' : $p['tela'] }}</strong></td>
                                    <td>{{ $p['bolsa'] }}</td>
                                    <td class="num">
                                        @if ($p['a_fixar_lotes'] > 0)
                                            <span class="badge badge--amber">{{ $p['a_fixar_lotes'] }}</span>
                                        @else
                                            <span style="color:var(--muted);">0</span>
                                        @endif
                                    </td>
                                    <td class="num">{{ number_format($p['a_fixar_sacas'], 0, ',', '.') }}</td>
                                    <td class="num">{{ $p['fixado_lotes'] ?: '—' }}</td>
                                    <td class="num">{{ $p['level_medio'] !== null ? number_format($p['level_medio'], 2, ',', '.') . ' ' . $p['unidade'] : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2">Total</td>
                                <td class="num"><strong>{{ array_sum(array_column($posicao, 'a_fixar_lotes')) }}</strong></td>
                                <td class="num"><strong>{{ number_format(array_sum(array_column($posicao, 'a_fixar_sacas')), 0, ',', '.') }}</strong></td>
                                <td class="num"><strong>{{ array_sum(array_column($posicao, 'fixado_lotes')) }}</strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="admin-grid">
        <div>
            <div class="card" style="margin-bottom:22px;">
                <div class="card__header card__header--dark mesh-texture">
                    <h2>Contratos a fixar</h2>
                </div>
                <div class="card__body" style="padding:0;">
                    <div class="table-wrap" style="border:0; border-radius:0;">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th>UT</th>
                                    <th>Comprador</th>
                                    <th>Bolsa</th>
                                    <th class="num">Diferencial</th>
                                    <th class="num">Lotes fixados</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($contratos as $c)
                                    <tr>
                                        <td><strong>UT {{ $c->numero_ut }}</strong></td>
                                        <td>{{ \Illuminate\Support\Str::limit($c->cliente_nome, 24) }}</td>
                                        <td>{{ $c->porto === 'VITORIA' ? 'Londres' : 'NY ICE' }}</td>
                                        <td class="num">{{ $c->diferencial !== null && $c->diferencial !== '' ? $c->diferencial . ' ' . $c->unidadePreco() : '—' }}</td>
                                        <td class="num">{{ $c->lotesFixados() }} / {{ $c->lotes }}</td>
                                        <td>
                                            @if ($c->parcialmenteFixado())
                                                <span class="badge badge--amber">PARCIAL {{ $c->lotesFixados() }}/{{ $c->lotes }}</span>
                                            @else
                                                <span class="badge badge--muted">A FIXAR</span>
                                            @endif
                                        </td>
                                        <td class="cell-action">
                                            <button type="button" class="btn btn-ghost js-escolher" data-contrato="{{ $c->id }}" style="padding:6px 12px; font-size:13px;">Fixar</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" style="text-align:center; color:var(--muted); padding:24px;">Nenhum contrato pendente de fixação. 🎉</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card__header"><h2>Fixações registradas</h2></div>
                <div class="card__body" style="padding:0;">
                    <div class="table-wrap" style="border:0; border-radius:0;">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Contrato</th>
                                    <th>Corretora</th>
                                    <th>Tela</th>
                                    <th class="num">Lotes</th>
                                    <th class="num">Level</th>
                                    <th class="num">Dif.</th>
                                    <th class="num">Preço</th>
                                    <th>Por</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($fixacoes as $f)
                                    <tr>
                                        <td>{{ $f->created_at->format('d/m/Y H:i') }}</td>
                                        <td><strong>UT {{ $f->contrato?->numero_ut }}</strong></td>
                                        <td>
                                            {{ $f->corretoraLabel() }}
                                            @if ($f->brokerClienteLabel())
                                                <br><span style="color:var(--muted); font-size:11.5px;">cliente: {{ $f->brokerClienteLabel() }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $f->tela ?: '—' }}</td>
                                        <td class="num">{{ $f->lotes }}</td>
                                        <td class="num">{{ number_format((float) $f->level, 2, ',', '.') }}</td>
                                        <td class="num">{{ number_format((float) $f->diferencial, 2, ',', '.') }}</td>
                                        <td class="num"><strong>{{ number_format((float) $f->preco, 2, ',', '.') }}</strong> {{ $f->contrato?->unidadePreco() }}</td>
                                        <td>{{ $f->criadoPor?->name ?? '—' }}</td>
                                        <td class="cell-action">
                                            <form method="POST" action="{{ route('ny.fixacoes.destroy', $f) }}"
                                                  onsubmit="return confirm('Excluir esta fixação? O saldo do contrato será recalculado (um contrato FIXED pode voltar a A FIXAR).');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-ghost" style="padding:6px 12px; font-size:13px; color:var(--danger-text);">Excluir</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10" style="text-align:center; color:var(--muted); padding:24px;">Nenhuma fixação registrada ainda.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card__header"><h2>Registrar fixação</h2></div>
            <div class="card__body">
                <form method="POST" action="{{ route('ny.fixacoes.store') }}">
                    @csrf

                    <div class="field {{ $errors->has('contratos') || $errors->has('diferenciais') || $errors->has('diferenciais.*') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                        <label>Contratos <span class="hint">(vários = fixa tudo de uma vez, na mesma tela)</span></label>
                        <div class="fix-list" id="fixList">
                            @forelse ($contratos as $c)
                                @php $marcado = in_array($c->id, (array) old('contratos', [])); @endphp
                                <div class="fix-item">
                                    <label>
                                        <input type="checkbox" name="contratos[]" value="{{ $c->id }}"
                                               data-restantes="{{ $c->lotesRestantes() }}"
                                               data-porto="{{ $c->porto }}"
                                               data-mes="{{ $c->mes_fixacao }}"
                                               data-unidade="{{ $c->unidadePreco() }}"
                                               data-ut="{{ $c->numero_ut }}"
                                               @checked($marcado)>
                                        <span>UT {{ $c->numero_ut }} — {{ \Illuminate\Support\Str::limit($c->cliente_nome, 14) }}</span>
                                        <span class="fix-item__meta">{{ $c->lotesRestantes() }} lt · {{ $c->porto === 'VITORIA' ? 'Londres' : 'NY' }}</span>
                                    </label>
                                    <input type="text" name="diferenciais[{{ $c->id }}]" class="fix-dif"
                                           value="{{ old('diferenciais.' . $c->id, (float) $c->diferencial != 0.0 ? number_format((float) $c->diferencial, 2, '.', '') : '') }}"
                                           placeholder="dif." inputmode="decimal"
                                           title="Diferencial do contrato UT {{ $c->numero_ut }}"
                                           {{ $marcado ? '' : 'disabled' }}>
                                </div>
                            @empty
                                <div style="color:var(--muted); font-size:13px; padding:6px 2px;">Nenhum contrato pendente.</div>
                            @endforelse
                        </div>
                        @error('contratos') <div class="field-error">{{ $message }}</div> @enderror
                        @error('diferenciais') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('corretora') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                        <label for="corretora">Corretora</label>
                        <select id="corretora" name="corretora" required>
                            @foreach (\App\Models\Fixacao::corretoras() as $cod => $rotulo)
                                <option value="{{ $cod }}" @selected(old('corretora') === $cod)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        @error('corretora') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('broker_cliente') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                        <label for="broker_cliente">Broker do cliente <span class="hint">(opcional)</span></label>
                        <select id="broker_cliente" name="broker_cliente">
                            <option value="">—</option>
                            @foreach (\App\Models\Fixacao::brokersCliente() as $cod => $rotulo)
                                <option value="{{ $cod }}" @selected(old('broker_cliente') === $cod)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        @error('broker_cliente') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('tela') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                        <label for="tela">Tela (mês da bolsa)</label>
                        <select id="tela" name="tela" data-old="{{ old('tela') }}" required>
                            <option value="">— marque um contrato —</option>
                        </select>
                        <span class="hint">Opções conforme a bolsa dos contratos marcados (NY ou Londres).</span>
                        @error('tela') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('lotes') ? 'has-error' : '' }}" style="margin-bottom:14px;" id="campoLotes">
                        <label for="lotes">Lotes a fixar <span class="hint" id="lotesHint"></span></label>
                        <input type="number" id="lotes" name="lotes" min="1" step="1" value="{{ old('lotes') }}">
                        @error('lotes') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="calc-note" id="grupoResumo" style="display:none; margin-bottom:14px;"></div>

                    <div class="field {{ $errors->has('level') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                        <label for="level">Level (<span id="levelUnit">cts/lb</span>)</label>
                        <input type="text" id="level" name="level" value="{{ old('level') }}" placeholder="Ex.: 335.00" inputmode="decimal" required>
                        @error('level') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="calc-note" style="margin-bottom:16px;">
                        <span id="pvPreco">—</span>
                        <span style="color:var(--muted);">(level + diferencial de cada contrato · conferido no servidor ao salvar)</span>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;">Registrar fixação</button>
                </form>
            </div>
        </div>
    </div>

<script>
    (function () {
        // Meses de fixação por bolsa (código => rótulo) — mesmos do contrato.
        var MESES = {
            SANTOS: @json(\App\Models\Contrato::mesesFixacaoSantos()),
            VITORIA: @json(\App\Models\Contrato::mesesFixacaoVitoria())
        };

        var lista = document.getElementById('fixList'),
            checks = Array.prototype.slice.call(lista.querySelectorAll('input[type=checkbox]')),
            telaEl = document.getElementById('tela'),
            lotesEl = document.getElementById('lotes'),
            lotesHint = document.getElementById('lotesHint'),
            campoLotes = document.getElementById('campoLotes'),
            grupoResumo = document.getElementById('grupoResumo'),
            levelEl = document.getElementById('level'),
            levelUnit = document.getElementById('levelUnit'),
            pvPreco = document.getElementById('pvPreco');

        function marcados() { return checks.filter(function (c) { return c.checked; }); }
        function difInput(cb) { return cb.closest('.fix-item').querySelector('.fix-dif'); }

        function montarTela(porto, preferido) {
            var atual = telaEl.value || telaEl.getAttribute('data-old') || preferido || '';
            telaEl.innerHTML = '<option value="">—</option>';
            var meses = MESES[porto] || {};
            Object.keys(meses).forEach(function (k) {
                var o = document.createElement('option');
                o.value = k; o.textContent = meses[k];
                if (k === atual) o.selected = true;
                telaEl.appendChild(o);
            });
            telaEl.removeAttribute('data-old');
        }

        function aoMudarSelecao() {
            var sel = marcados();

            // Habilita o diferencial só dos marcados (desabilitado não é enviado).
            checks.forEach(function (cb) { difInput(cb).disabled = !cb.checked; });

            if (!sel.length) {
                checks.forEach(function (cb) { cb.disabled = false; });
                telaEl.innerHTML = '<option value="">— marque um contrato —</option>';
                campoLotes.style.display = '';
                grupoResumo.style.display = 'none';
                lotesEl.disabled = false;
                lotesHint.textContent = '';
                preview();
                return;
            }

            // Grupo só entre contratos da mesma bolsa: trava os demais.
            var porto = sel[0].getAttribute('data-porto');
            checks.forEach(function (cb) {
                cb.disabled = !cb.checked && cb.getAttribute('data-porto') !== porto;
            });

            montarTela(porto, sel[0].getAttribute('data-mes'));
            levelUnit.textContent = sel[0].getAttribute('data-unidade') || 'cts/lb';

            if (sel.length === 1) {
                var restantes = parseInt(sel[0].getAttribute('data-restantes') || '0', 10);
                campoLotes.style.display = '';
                grupoResumo.style.display = 'none';
                lotesEl.disabled = false;
                lotesEl.max = restantes;
                if (!lotesEl.value || parseInt(lotesEl.value, 10) > restantes) lotesEl.value = restantes;
                lotesHint.textContent = '(máx. ' + restantes + ')';
            } else {
                // Grupo: fixa todos os lotes restantes de todos — sem campo manual.
                var total = sel.reduce(function (s, cb) { return s + parseInt(cb.getAttribute('data-restantes') || '0', 10); }, 0);
                campoLotes.style.display = 'none';
                lotesEl.disabled = true; // não envia o campo no modo grupo
                grupoResumo.style.display = '';
                grupoResumo.innerHTML = 'Fixação em grupo: <strong>' + total + ' lote(s)</strong> — todos os restantes dos '
                    + sel.length + ' contratos marcados.';
            }
            preview();
        }

        function num(txt) { return parseFloat((txt || '').replace(',', '.')); }

        function preview() {
            var sel = marcados(), lv = num(levelEl.value);
            if (!sel.length || isNaN(lv)) { pvPreco.textContent = '—'; return; }

            var unidade = levelUnit.textContent, partes = [], somaPeso = 0, somaLotes = 0;
            sel.forEach(function (cb) {
                var df = num(difInput(cb).value);
                var preco = lv + (isNaN(df) ? 0 : df);
                var lotes = sel.length === 1
                    ? (parseInt(lotesEl.value, 10) || 0)
                    : parseInt(cb.getAttribute('data-restantes') || '0', 10);
                somaPeso += preco * lotes; somaLotes += lotes;
                partes.push('UT ' + cb.getAttribute('data-ut') + ': ' + preco.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            });

            var texto = partes.join(' · ');
            if (sel.length > 1 && somaLotes > 0) {
                texto += ' — média ' + (somaPeso / somaLotes).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            pvPreco.innerHTML = '<strong>' + texto + ' ' + unidade + '</strong>';
        }

        checks.forEach(function (cb) { cb.addEventListener('change', aoMudarSelecao); });
        lista.addEventListener('input', function (e) { if (e.target.classList.contains('fix-dif')) preview(); });
        [levelEl, lotesEl].forEach(function (el) { el.addEventListener('input', preview); });

        // Botão "Fixar" na tabela: marca o contrato no formulário.
        document.querySelectorAll('.js-escolher').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cb = lista.querySelector('input[value="' + btn.getAttribute('data-contrato') + '"]');
                if (cb && !cb.disabled) {
                    cb.checked = true;
                    aoMudarSelecao();
                    cb.closest('.fix-item').scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        });

        aoMudarSelecao();

        // Régua de cotações: melhor esforço — se a API falhar, a régua
        // simplesmente não aparece (a tela de fixação não depende dela).
        fetch('{{ route('mercado.api') }}', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (m) {
                if (!m) return;
                var strip = document.getElementById('mktStrip');
                function fmt(v, dec) { return v == null ? '—' : v.toLocaleString('pt-BR', { minimumFractionDigits: dec, maximumFractionDigits: dec }); }
                function setDif(sel, v, dec) {
                    var el = strip.querySelector('[data-mkt-dif="' + sel + '"]');
                    if (v == null) { el.textContent = ''; return; }
                    el.textContent = (v >= 0 ? '+' : '') + fmt(v, dec);
                    el.className = 'mkt-dif ' + (v >= 0 ? 'mkt-up' : 'mkt-down');
                }
                var kc = (m.arabica || [])[0], rc = (m.robusta || [])[0];
                if (kc && kc.price != null) {
                    strip.querySelector('[data-mkt-lbl="kc"]').textContent = kc.code;
                    strip.querySelector('[data-mkt="kc"]').textContent = fmt(kc.price, 2);
                    setDif('kc', kc.dif, 2);
                }
                if (rc && rc.price != null) {
                    strip.querySelector('[data-mkt-lbl="rc"]').textContent = rc.code;
                    strip.querySelector('[data-mkt="rc"]').textContent = fmt(rc.price, 0);
                    setDif('rc', rc.dif, 0);
                }
                if (m.cambio && m.cambio.dolar && m.cambio.dolar.value != null) {
                    strip.querySelector('[data-mkt="usd"]').textContent = 'R$ ' + fmt(m.cambio.dolar.value, 4);
                    setDif('usd', m.cambio.dolar.dif, 4);
                }
                strip.hidden = false;
            })
            .catch(function () { /* sem cotações, sem drama */ });
    })();
</script>
@endsection
