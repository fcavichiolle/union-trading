{{-- Formulário de contrato, compartilhado por "Novo contrato" e "Editar
     contrato". Espera:
       $contrato  — null na criação, o modelo na edição
       $clientes, $qualidades
     Os valores usam old(campo, valor-do-contrato), então um erro de
     validação preserva o que o usuário digitou sem perder o original. --}}
@php
    $cafe = \App\Models\Contrato::class;
    $editando = $contrato !== null;
    // Com tranches na Tela NY, o estado do preço vem de lá — o checkbox
    // manual seria sobrescrito por Contrato::recalcularFixacao().
    $temFixacoes = $editando && $contrato->lotesFixados() > 0;

    $valor = fn (string $campo, $padrao = null) => old($campo, $editando ? ($contrato->$campo ?? $padrao) : $padrao);

    // Posições de bolsa: o dropdown oferece só as EM ABERTO (a lista se
    // atualiza sozinha com o passar dos meses). Mas se este contrato está
    // fixado numa posição que já venceu, ela entra na lista marcada como
    // vencida — sem isso, abrir e salvar o contrato apagaria o mês gravado.
    $mesesSantos = \App\Models\Contrato::mesesFixacaoSantos();
    $mesesVitoria = \App\Models\Contrato::mesesFixacaoVitoria();
    $telaAtual = $valor('mes_fixacao');

    if ($telaAtual && ! isset($mesesSantos[$telaAtual]) && ! isset($mesesVitoria[$telaAtual])) {
        $rotuloVencido = \App\Models\Contrato::rotuloDaTela($telaAtual) . ' — já vencida';

        if (\App\Models\Contrato::telaEhDeLondres($telaAtual)) {
            $mesesVitoria = [$telaAtual => $rotuloVencido] + $mesesVitoria;
        } else {
            $mesesSantos = [$telaAtual => $rotuloVencido] + $mesesSantos;
        }
    }
@endphp

<div class="contract-cols">
    <div class="contract-col">
        {{-- 1. Informações Gerais --}}
        <div class="card">
            <div class="card__header"><h2>1. Informações Gerais</h2></div>
            <div class="card__body">
                <div class="field {{ $errors->has('data_contrato') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                    <label for="data_contrato">Data do contrato</label>
                    <input type="date" id="data_contrato" name="data_contrato"
                           value="{{ old('data_contrato', $editando ? $contrato->data_contrato->format('Y-m-d') : date('Y-m-d')) }}" required>
                    @error('data_contrato') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field {{ $errors->has('numero_ut') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                    <label for="numero_ut">Ref. Vendedor UT (número do contrato)</label>
                    <input type="text" id="numero_ut" name="numero_ut" value="{{ $valor('numero_ut') }}" placeholder="Ex.: 5940" required>
                    @error('numero_ut') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field {{ $errors->has('cliente_id') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                    <label for="cliente_id">Comprador (BUYER)</label>
                    <select id="cliente_id" name="cliente_id" required>
                        <option value="">Selecione…</option>
                        @foreach ($clientes as $c)
                            <option value="{{ $c->id }}" data-ref="{{ $c->ref_padrao }}" @selected($valor('cliente_id') == $c->id)>{{ $c->nome }}</option>
                        @endforeach
                    </select>
                    @error('cliente_id') <div class="field-error">{{ $message }}</div> @enderror
                    @if ($clientes->isEmpty())
                        <div class="hint">Nenhum cliente cadastrado — <a href="{{ route('admin.clientes.index') }}" style="text-decoration:underline;">cadastre um cliente</a> primeiro.</div>
                    @endif
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="buyer_ref">Ref. Comprador</label>
                    <input type="text" id="buyer_ref" name="buyer_ref" value="{{ $valor('buyer_ref') }}" placeholder="Ex.: 31722">
                </div>
            </div>
        </div>

        {{-- 3. Preço e Logística --}}
        <div class="card">
            <div class="card__header"><h2>3. Preço e Logística</h2></div>
            <div class="card__body">
                @if ($temFixacoes)
                    <div class="alert" style="background:#FCF3DC; color:#8A6116; border:1px solid #EBD9A8; font-size:13px;">
                        Este contrato tem <strong>{{ $contrato->lotesFixados() }} lote(s) fixado(s)</strong> na Tela NY.
                        O preço e o status (FIXED / PARCIAL) vêm das fixações — o que for marcado aqui será recalculado ao salvar.
                    </div>
                @endif

                <div class="field" style="margin-bottom:14px;">
                    <label class="check-row">
                        <input type="checkbox" id="fixado" name="fixado" value="1" @checked($valor('fixado'))>
                        <span>Contrato já fixado (FIXED)</span>
                    </label>
                </div>

                <div id="grupoAFixar">
                    <div class="field" style="margin-bottom:14px;">
                        <label for="diferencial">Diferencial (<span id="difUnit">cents/pounds</span>)</label>
                        <input type="text" id="diferencial" name="diferencial" value="{{ $valor('diferencial') }}" placeholder="Ex.: -16.00">
                    </div>
                    <div class="field" style="margin-bottom:14px;">
                        <label for="mes_fixacao">Mês de fixação (bolsa)</label>
                        <select id="mes_fixacao" name="mes_fixacao" data-old="{{ $valor('mes_fixacao') }}">
                            <option value="">—</option>
                        </select>
                        <span class="hint">As opções mudam conforme o porto (Santos = NY ICE · Vitória = Robusta Londres).</span>
                    </div>
                </div>

                <div id="grupoFixado" style="display:none;">
                    <div style="display:flex; gap:10px; margin-bottom:14px;">
                        <div class="field {{ $errors->has('preco_fixado') ? 'has-error' : '' }}" style="flex:1; margin-bottom:0;">
                            <label for="preco_fixado">Preço fixado</label>
                            <input type="text" id="preco_fixado" name="preco_fixado" value="{{ $valor('preco_fixado') }}" placeholder="Ex.: 353.40">
                            @error('preco_fixado') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field {{ $errors->has('preco_fixado_unidade') ? 'has-error' : '' }}" style="width:130px; margin-bottom:0;">
                            <label for="preco_fixado_unidade">Unidade</label>
                            <select id="preco_fixado_unidade" name="preco_fixado_unidade" data-old="{{ $valor('preco_fixado_unidade') }}">
                                @foreach ($cafe::unidadesPreco() as $cod => $rotulo)
                                    <option value="{{ $cod }}" @selected($valor('preco_fixado_unidade') === $cod)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            @error('preco_fixado_unidade') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="field {{ $errors->has('embarque_mes') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                    <label for="embarque_mes">Embarque (mês)</label>
                    <input type="month" id="embarque_mes" name="embarque_mes"
                           value="{{ old('embarque_mes', $editando && $contrato->embarque_mes ? $contrato->embarque_mes->format('Y-m') : '') }}">
                    @error('embarque_mes') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="field" style="margin-bottom:14px;">
                    <label for="incoterms">Incoterms</label>
                    <select id="incoterms" name="incoterms" required>
                        @foreach ($cafe::incotermsLista() as $cod => $ext)
                            <option value="{{ $cod }}" @selected($valor('incoterms', 'FOB') === $cod)>{{ $cod }} — {{ $ext }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="porto">Porto</label>
                    <select id="porto" name="porto" required>
                        @foreach ($cafe::portos() as $cod => $rotulo)
                            <option value="{{ $cod }}" @selected($valor('porto') === $cod)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="contract-col">
        {{-- 2. Detalhes do Produto --}}
        <div class="card">
            <div class="card__header"><h2>2. Detalhes do Produto</h2></div>
            <div class="card__body">
                <div class="field {{ $errors->has('quantidade_kg') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                    <label for="quantidade_kg">Quantidade (KG)</label>
                    <input type="number" step="0.01" min="1" id="quantidade_kg" name="quantidade_kg"
                           value="{{ old('quantidade_kg', $editando ? rtrim(rtrim((string) $contrato->quantidade_kg, '0'), '.') : '') }}"
                           placeholder="Ex.: 108000" required>
                    @error('quantidade_kg') <div class="field-error">{{ $message }}</div> @enderror
                    @if ($temFixacoes)
                        <span class="hint">Não pode ficar abaixo de {{ $contrato->lotesFixados() }} lote(s) — o que já está fixado.</span>
                    @endif
                </div>
                <div class="field {{ $errors->has('qualidade_id') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                    <label for="qualidade_id">Qualidade</label>
                    <select id="qualidade_id" name="qualidade_id" required>
                        <option value="">Selecione…</option>
                        @foreach ($qualidades as $q)
                            <option value="{{ $q->id }}" @selected($valor('qualidade_id') == $q->id)>{{ $q->descricao }}</option>
                        @endforeach
                    </select>
                    @error('qualidade_id') <div class="field-error">{{ $message }}</div> @enderror
                    @if ($qualidades->isEmpty())
                        <div class="hint">Nenhuma qualidade cadastrada — <a href="{{ route('admin.qualidades.index') }}" style="text-decoration:underline;">cadastre uma qualidade</a> primeiro.</div>
                    @endif
                </div>
                <div class="field" style="margin-bottom:14px;">
                    <label for="tipo_cafe">Tipo de café (define o cálculo de lotes)</label>
                    <select id="tipo_cafe" name="tipo_cafe" required>
                        <option value="ARABICA" @selected($valor('tipo_cafe', 'ARABICA') === 'ARABICA')>Arábica (÷ 283,49)</option>
                        <option value="CONILON" @selected($valor('tipo_cafe') === 'CONILON')>Conilon (÷ 166,66)</option>
                    </select>
                </div>
                <div class="field" style="margin-bottom:14px;">
                    <label for="certificado">Certificado</label>
                    <select id="certificado" name="certificado" required>
                        @foreach ($cafe::certificados() as $cod => $rotulo)
                            <option value="{{ $cod }}" @selected($valor('certificado') === $cod)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom:14px;">
                    <label for="embalagem">Embalagem</label>
                    <select id="embalagem" name="embalagem" required>
                        @foreach ($cafe::embalagens() as $emb)
                            <option value="{{ $emb }}" @selected($valor('embalagem') === $emb)>{{ $emb }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="tipo_container">Tipo de container</label>
                    {{-- TEUS (20') vem pré-selecionado por pedido da mesa. Ao
                         editar um contrato, vale o que está gravado nele. --}}
                    <select id="tipo_container" name="tipo_container" required>
                        <option value="20" @selected((string) $valor('tipo_container', '20') === '20')>TEUS — Container de 20' (máx. 22.000 kg)</option>
                        <option value="40" @selected((string) $valor('tipo_container') === '40')>FEUS — Container de 40' (máx. 25.000 kg)</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- 4. Observações --}}
        <div class="card">
            <div class="card__header"><h2>4. Observações Adicionais (REMARKS)</h2></div>
            <div class="card__body">
                <div class="field" style="margin-bottom:0;">
                    <textarea name="remarks" rows="8" placeholder="Ex.: SHIPMENT 01/09" style="width:100%; resize:vertical;">{{ $valor('remarks') }}</textarea>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Preview do cálculo --}}
<div class="card calc-preview" style="margin-top:20px;">
    <div class="card__header"><h2>Resumo do cálculo (automático)</h2></div>
    <div class="card__body">
        <div class="calc-grid">
            <div class="calc-item"><span class="calc-lbl">Sacas (kg ÷ 60 / 59)</span><span class="calc-val" id="pvSacas">—</span></div>
            <div class="calc-item"><span class="calc-lbl">Lotes</span><span class="calc-val" id="pvLotes">—</span></div>
            <div class="calc-item"><span class="calc-lbl">Containers</span><span class="calc-val" id="pvContainers">—</span></div>
            <div class="calc-item"><span class="calc-lbl">Peso por container</span><span class="calc-val" id="pvPeso">—</span></div>
        </div>
        <p class="calc-note" id="pvFrase">Preencha a quantidade e o tipo de café para ver o cálculo.</p>
    </div>
</div>

<script>
    (function () {
        var DIV = { ARABICA: 283.49, CONILON: 166.66 }, CAP = { '20': 22000, '40': 25000 };
        // Listas vindas do PHP (posições em aberto + a vencida deste
        // contrato, quando for o caso) — ver o @php no topo desta partial.
        var MESES = { SANTOS: @json($mesesSantos), VITORIA: @json($mesesVitoria) };

        var kgEl = document.getElementById('quantidade_kg'),
            tipoEl = document.getElementById('tipo_cafe'),
            contEl = document.getElementById('tipo_container'),
            embEl = document.getElementById('embalagem'),
            portoEl = document.getElementById('porto'),
            clienteEl = document.getElementById('cliente_id'),
            buyerRefEl = document.getElementById('buyer_ref'),
            mesEl = document.getElementById('mes_fixacao'),
            difUnit = document.getElementById('difUnit'),
            fixadoEl = document.getElementById('fixado'),
            grupoAFixar = document.getElementById('grupoAFixar'),
            grupoFixado = document.getElementById('grupoFixado'),
            precoFixadoEl = document.getElementById('preco_fixado'),
            precoFixadoUnidadeEl = document.getElementById('preco_fixado_unidade');

        function toggleFixado() {
            var fixado = fixadoEl.checked;
            grupoAFixar.style.display = fixado ? 'none' : '';
            grupoFixado.style.display = fixado ? '' : 'none';
            precoFixadoEl.required = fixado;
        }

        function fmt(n, dec) { return n.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: dec }); }
        function kgPorSaca() { return embEl.value === 'Jute Bags 59kg' ? 59 : 60; }

        function buildMeses() {
            var lista = portoEl.value === 'VITORIA' ? MESES.VITORIA : MESES.SANTOS;
            var alvo = mesEl.getAttribute('data-old') || '';
            mesEl.innerHTML = '<option value="">—</option>';
            Object.keys(lista).forEach(function (k) {
                var o = document.createElement('option');
                o.value = k; o.textContent = lista[k];
                if (k === alvo) o.selected = true;
                mesEl.appendChild(o);
            });
            if (difUnit) difUnit.textContent = portoEl.value === 'VITORIA' ? 'USD/MT' : 'cents/pounds';
            // Sugere a unidade "de costume" do porto, mas só se o usuário
            // ainda não escolheu a unidade do preço fixado manualmente —
            // é uma escolha livre, não amarrada ao porto.
            if (precoFixadoUnidadeEl && !precoFixadoUnidadeEl.dataset.tocado) {
                precoFixadoUnidadeEl.value = portoEl.value === 'VITORIA' ? 'USD_MT' : 'CTS_LB';
            }
        }

        function preview() {
            var kg = parseFloat(kgEl.value);
            var frase = document.getElementById('pvFrase');
            if (!kg || kg <= 0) {
                ['pvSacas', 'pvLotes', 'pvContainers', 'pvPeso'].forEach(function (id) { document.getElementById(id).textContent = '—'; });
                frase.textContent = 'Preencha a quantidade e o tipo de café para ver o cálculo.';
                return;
            }
            var sacas = kg / kgPorSaca();
            var lotes = Math.round(sacas / DIV[tipoEl.value]);
            var cap = CAP[contEl.value] || CAP['40'];
            var containers = Math.max(1, Math.ceil(kg / cap));
            var pesoCada = kg / containers;

            document.getElementById('pvSacas').textContent = fmt(sacas, 2) + ' (÷ ' + kgPorSaca() + ' kg)';
            document.getElementById('pvLotes').textContent = lotes;
            document.getElementById('pvContainers').textContent = containers;
            document.getElementById('pvPeso').textContent = fmt(pesoCada, 2) + ' kg (' + fmt(pesoCada / 1000, 2) + ' ton)';
            frase.textContent = fmt(kg, 2) + ' kilos → ' + containers + ' container(s) de ' + fmt(pesoCada / 1000, 2) + ' ton · ' + lotes + ' lote(s)';
        }

        [kgEl, tipoEl, contEl, embEl].forEach(function (el) { el.addEventListener('input', preview); el.addEventListener('change', preview); });
        portoEl.addEventListener('change', function () { mesEl.removeAttribute('data-old'); buildMeses(); });
        clienteEl.addEventListener('change', function () {
            var opt = clienteEl.options[clienteEl.selectedIndex];
            var ref = opt ? (opt.getAttribute('data-ref') || '') : '';
            if (ref) buyerRefEl.value = ref;
        });
        fixadoEl.addEventListener('change', toggleFixado);
        if (precoFixadoUnidadeEl) {
            // Se já veio um valor (reenvio após erro de validação, ou edição
            // de contrato existente), respeita a escolha em vez de
            // sobrescrever com a sugestão do porto.
            if (precoFixadoUnidadeEl.getAttribute('data-old')) precoFixadoUnidadeEl.dataset.tocado = '1';
            precoFixadoUnidadeEl.addEventListener('change', function () { precoFixadoUnidadeEl.dataset.tocado = '1'; });
        }

        buildMeses();
        preview();
        toggleFixado();
    })();
</script>
