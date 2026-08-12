@extends('layouts.app')

@php
    $editando = $compra !== null;
    $valor = fn (string $campo, $padrao = null) => old($campo, $editando ? ($compra->$campo ?? $padrao) : $padrao);
@endphp

@section('title', $editando ? 'Editar compra — ' . $compra->uts : 'Nova compra')
@section('subtitle', 'Dados do negócio fechado. O que realmente entrar no armazém é lançado depois, como entrega.')

@section('crumb')
    <span>Compras &amp; Classificação</span><span class="sep">/</span>
    <b>{{ $editando ? 'Editar ' . $compra->uts : 'Nova compra' }}</b>
@endsection

@section('content')
<form method="POST" action="{{ $editando ? route('compras.update', $compra) : route('compras.store') }}">
    @csrf
    @if ($editando) @method('PUT') @endif

    <div class="contract-cols">
        <div class="contract-col">
            <div class="card">
                <div class="card__header"><h2>1. Negócio</h2></div>
                <div class="card__body">
                    <div style="display:flex; gap:12px; margin-bottom:14px;">
                        <div class="field {{ $errors->has('uts') ? 'has-error' : '' }}" style="flex:1; margin-bottom:0;">
                            <label for="uts">UTS (ref. de compra)</label>
                            <input type="text" id="uts" name="uts" value="{{ $valor('uts') }}" placeholder="Ex.: UTS 7312" required>
                            @error('uts') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field {{ $errors->has('data_compra') ? 'has-error' : '' }}" style="width:180px; margin-bottom:0;">
                            <label for="data_compra">Data da compra</label>
                            <input type="date" id="data_compra" name="data_compra"
                                   value="{{ old('data_compra', $editando ? $compra->data_compra?->format('Y-m-d') : date('Y-m-d')) }}" required>
                            @error('data_compra') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="field {{ $errors->has('fornecedor_documento') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                        <label for="fornecedor_documento">CNPJ / CPF do vendedor <span class="hint">(opcional — pode ficar "a confirmar")</span></label>
                        <div style="display:flex; gap:8px;">
                            <input type="text" id="fornecedor_documento" name="fornecedor_documento"
                                   value="{{ old('fornecedor_documento', $editando ? $compra->fornecedor?->documento : '') }}"
                                   placeholder="00.000.000/0000-00 ou 000.000.000-00" style="flex:1;">
                            <button type="button" class="btn btn-ghost" id="btnBuscarCnpj" style="flex:none;">Buscar nome</button>
                        </div>
                        <span class="hint" id="avisoCnpj">Com CNPJ o nome é preenchido automaticamente. Para CPF, digite o nome (não existe consulta pública por CPF).</span>
                        @error('fornecedor_documento') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field {{ $errors->has('fornecedor_nome') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                        <label for="fornecedor_nome">Vendedor (nome)</label>
                        <input type="text" id="fornecedor_nome" name="fornecedor_nome"
                               value="{{ old('fornecedor_nome', $editando ? $compra->fornecedor?->nome : '') }}"
                               {{-- Exemplo genérico: este placeholder vai para a demo
                                    pública, e nome de fornecedor real não vai ao ar. --}}
                               placeholder="Ex.: FAZENDA SANTA CLARA LTDA" required>
                        @error('fornecedor_nome') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    {{-- Sacas e peso: preencher um calcula o outro (60 kg/saca).
                         O armazém informa às vezes um, às vezes o outro. --}}
                    <div style="display:flex; gap:12px; margin-bottom:14px;">
                        <div class="field {{ $errors->has('volume_contratado') ? 'has-error' : '' }}" style="flex:1; margin-bottom:0;">
                            <label for="volume_contratado">Volume contratado (sacas)</label>
                            <input type="number" step="0.01" min="0.01" id="volume_contratado" name="volume_contratado"
                                   value="{{ $valor('volume_contratado') }}" placeholder="Ex.: 500" required>
                            @error('volume_contratado') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field {{ $errors->has('peso_kg') ? 'has-error' : '' }}" style="flex:1; margin-bottom:0;">
                            <label for="peso_kg">Peso (kg)</label>
                            <input type="number" step="0.01" min="0.01" id="peso_kg" name="peso_kg"
                                   value="{{ $valor('peso_kg') }}" placeholder="Ex.: 30000">
                            <span class="hint">Um preenche o outro, a 60 kg por saca.</span>
                            @error('peso_kg') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div style="display:flex; gap:12px; margin-bottom:14px;">
                        <div class="field {{ $errors->has('tipo_entrada') ? 'has-error' : '' }}" style="flex:1; margin-bottom:0;">
                            <label for="tipo_entrada">Tipo de café</label>
                            <select id="tipo_entrada" name="tipo_entrada" required>
                                @foreach (\App\Models\Compra::tiposEntrada() as $cod => $rotulo)
                                    <option value="{{ $cod }}" @selected($valor('tipo_entrada', 'ARABICA') === $cod)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            <span class="hint">Conilon não usa padrão nem bebida.</span>
                            @error('tipo_entrada') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field" style="flex:1; margin-bottom:0;"></div>
                    </div>

                    {{-- Qualidade negociada. Some inteiro quando o café é
                         conilon (JS abaixo), e o servidor grava nulo. --}}
                    <div style="display:flex; gap:12px; margin-bottom:14px;" id="blocoQualidade">
                        <div class="field {{ $errors->has('padrao_final') ? 'has-error' : '' }}" style="flex:1; margin-bottom:0;">
                            <label for="padrao_final">Padrão final</label>
                            <select id="padrao_final" name="padrao_final">
                                <option value="">Selecione...</option>
                                @foreach (\App\Models\Classificacao::padroes() as $cod => $rotulo)
                                    <option value="{{ $cod }}" @selected($valor('padrao_final') === $cod)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            @error('padrao_final') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field {{ $errors->has('tipo_bebida') ? 'has-error' : '' }}" style="flex:1; margin-bottom:0;">
                            <label for="tipo_bebida">Tipo de bebida</label>
                            <select id="tipo_bebida" name="tipo_bebida">
                                <option value="">Selecione...</option>
                                @foreach (\App\Models\Classificacao::tiposBebida() as $cod => $rotulo)
                                    <option value="{{ $cod }}" @selected($valor('tipo_bebida') === $cod)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            @error('tipo_bebida') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div style="display:flex; gap:12px; margin-bottom:0;">
                        <div class="field {{ $errors->has('certificacao') ? 'has-error' : '' }}" style="flex:1; margin-bottom:0;">
                            <label for="certificacao">Certificação</label>
                            <select id="certificacao" name="certificacao" required>
                                <option value="">Selecione...</option>
                                @foreach (\App\Models\Compra::certificacoes() as $cod => $rotulo)
                                    <option value="{{ $cod }}" @selected($valor('certificacao') === $cod)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            @error('certificacao') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field" style="flex:1; margin-bottom:0;">
                            <label for="logistica">Logística</label>
                            <select id="logistica" name="logistica">
                                <option value="">—</option>
                                @foreach (\App\Models\Compra::logisticas() as $cod => $rotulo)
                                    <option value="{{ $cod }}" @selected($valor('logistica') === $cod)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            <span class="hint">Posto = o vendedor entrega. Retirar = nós buscamos.</span>
                        </div>
                    </div>

                    {{-- Armazém combinado no negócio. Opcional: às vezes o destino
                         só se define na hora de entregar. Serve para já vir
                         escolhido no lançamento da entrega — quem vale para o
                         estoque é o armazém de cada entrega. --}}
                    <div style="display:flex; gap:12px; margin-top:14px; margin-bottom:0;">
                        <div class="field {{ $errors->has('armazem_id') ? 'has-error' : '' }}" style="flex:1; margin-bottom:0;">
                            <label for="armazem_id">Armazém previsto <span class="hint">(opcional)</span></label>
                            <select id="armazem_id" name="armazem_id">
                                <option value="">— a definir</option>
                                @foreach (\App\Models\Armazem::lista() as $id => $nome)
                                    <option value="{{ $id }}" @selected((int) $valor('armazem_id') === $id)>{{ $nome }}</option>
                                @endforeach
                            </select>
                            <span class="hint">Vem pré-selecionado ao lançar a entrega; o café pode chegar em outro.</span>
                            @error('armazem_id') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field" style="flex:1; margin-bottom:0;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="contract-col">
            <div class="card">
                <div class="card__header"><h2>2. Preço e pagamento</h2></div>
                <div class="card__body">
                    <div class="field {{ $errors->has('valor_saca') ? 'has-error' : '' }}" style="margin-bottom:14px;">
                        <label for="valor_saca">Preço por saca (R$) <span class="hint">(opcional)</span></label>
                        <input type="number" step="0.01" min="0" id="valor_saca" name="valor_saca"
                               value="{{ $valor('valor_saca') }}" placeholder="Ex.: 1630.00">
                        <span class="hint" id="previaTotal"></span>
                        @error('valor_saca') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div style="display:flex; gap:12px; margin-bottom:14px;">
                        <div class="field" style="flex:1; margin-bottom:0;">
                            <label for="corretor_nome">Corretor</label>
                            <input type="text" id="corretor_nome" name="corretor_nome" value="{{ $valor('corretor_nome') }}" placeholder="Ex.: Corretora Aurora">
                        </div>
                        <div class="field {{ $errors->has('comissao_pct') ? 'has-error' : '' }}" style="width:130px; margin-bottom:0;">
                            <label for="comissao_pct">Comissão (%)</label>
                            <input type="number" step="0.01" min="0" max="100" id="comissao_pct" name="comissao_pct"
                                   value="{{ $valor('comissao_pct') }}" placeholder="0,50">
                            @error('comissao_pct') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="field" style="margin-bottom:14px;">
                        <label for="pagamento_previsto">Pagamento previsto</label>
                        <input type="date" id="pagamento_previsto" name="pagamento_previsto"
                               value="{{ old('pagamento_previsto', $editando ? $compra->pagamento_previsto?->format('Y-m-d') : '') }}">
                    </div>

                    <div class="field" style="margin-bottom:0;">
                        <label for="pagamento_obs">Observação do pagamento</label>
                        <input type="text" id="pagamento_obs" name="pagamento_obs" value="{{ $valor('pagamento_obs') }}"
                               placeholder="Ex.: 90% dia 14/08, saldo na classificação">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card__header"><h2>Como funciona daqui</h2></div>
                <div class="card__body">
                    <p style="margin:0; font-size:13px; line-height:1.6; color:var(--muted);">
                        Esta tela guarda o <strong>negócio</strong>. Conforme o café for entrando no armazém,
                        cada entrada é lançada na tela da compra como uma <strong>entrega</strong>, com o armazém,
                        o mês, as sacas que realmente chegaram e o número do lote.
                        A mesma UTS pode ter várias entregas — inclusive em armazéns diferentes — e só entra
                        no estoque a entrega que já tem número de lote.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex; gap:14px; margin-top:22px;">
        <button type="submit" class="btn-coffee">
            <span>{{ $editando ? 'Salvar alterações' : 'Registrar compra' }}</span>
            <span class="bean"><svg viewBox="0 0 24 32" width="15" height="20"><ellipse cx="12" cy="16" rx="10.5" ry="15" fill="#E9E2D1"></ellipse><path d="M12 2.5c3.2 6 3.2 21.5 0 27" stroke="#0A3A22" stroke-width="2.2" fill="none"></path></svg></span>
        </button>
        <a href="{{ $editando ? route('compras.show', $compra) : route('compras.index') }}" class="btn btn-ghost">Cancelar</a>
    </div>
</form>

<script>
    (function () {
        var docEl = document.getElementById('fornecedor_documento'),
            nomeEl = document.getElementById('fornecedor_nome'),
            btn = document.getElementById('btnBuscarCnpj'),
            aviso = document.getElementById('avisoCnpj'),
            valorEl = document.getElementById('valor_saca'),
            volumeEl = document.getElementById('volume_contratado'),
            previa = document.getElementById('previaTotal');

        function digitos(v) { return (v || '').replace(/\D/g, ''); }

        // Busca por CNPJ é conveniência: qualquer falha só avisa, nunca
        // impede o lançamento (o nome pode ser digitado à mão).
        function buscar() {
            var d = digitos(docEl.value);

            if (d.length === 11) {
                aviso.textContent = 'CPF não tem consulta pública de nome — digite o nome do vendedor.';
                nomeEl.focus();
                return;
            }
            if (d.length !== 14) {
                aviso.textContent = 'Digite um CNPJ com 14 dígitos para buscar o nome.';
                return;
            }

            aviso.textContent = 'Consultando CNPJ…';
            btn.disabled = true;

            fetch('{{ url('compras/cnpj') }}/' + d, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (dados) {
                    nomeEl.value = dados.nome;
                    aviso.textContent = 'Encontrado: ' + dados.nome
                        + (dados.situacao ? ' · ' + dados.situacao : '');
                })
                .catch(function () {
                    aviso.textContent = 'Não foi possível consultar agora — digite o nome do vendedor.';
                })
                .finally(function () { btn.disabled = false; });
        }

        btn.addEventListener('click', buscar);
        docEl.addEventListener('blur', function () {
            if (digitos(docEl.value).length === 14 && !nomeEl.value) buscar();
        });

        function previewTotal() {
            var v = parseFloat(valorEl.value), q = parseFloat(volumeEl.value);
            if (!v || !q) { previa.textContent = ''; return; }
            previa.textContent = 'Valor contratado: R$ '
                + (v * q).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                + ' — o valor efetivo será calculado sobre o que realmente entrar.';
        }
        [valorEl, volumeEl].forEach(function (el) { el.addEventListener('input', previewTotal); });
        previewTotal();

        // ---- Sacas <-> peso (60 kg/saca) ----
        // Só completa o campo que o usuário NÃO está preenchendo; quem digita
        // manda. O servidor faz a mesma conta, então funciona sem JS também.
        var KG_POR_SACA = {{ \App\Models\Compra::KG_POR_SACA }},
            pesoEl = document.getElementById('peso_kg');

        function arredonda(n) { return Math.round(n * 100) / 100; }

        volumeEl.addEventListener('input', function () {
            var sacas = parseFloat(volumeEl.value);
            pesoEl.value = sacas > 0 ? arredonda(sacas * KG_POR_SACA) : '';
        });

        pesoEl.addEventListener('input', function () {
            var kg = parseFloat(pesoEl.value);
            volumeEl.value = kg > 0 ? arredonda(kg / KG_POR_SACA) : '';
            previewTotal();
        });

        // ---- Conilon não tem padrão/bebida ----
        var tipoEl = document.getElementById('tipo_entrada'),
            qualidade = document.getElementById('blocoQualidade');

        function sincronizarQualidade() {
            var conilon = tipoEl.value === 'CONILON';
            qualidade.style.display = conilon ? 'none' : 'flex';

            // Escondido e desabilitado: sem isso o navegador ainda enviaria
            // o padrão de um arábica que virou conilon no meio do
            // preenchimento.
            qualidade.querySelectorAll('select').forEach(function (el) {
                el.disabled = conilon;
                if (conilon) el.value = '';
            });
        }

        tipoEl.addEventListener('change', sincronizarQualidade);
        sincronizarQualidade();
    })();
</script>
@endsection
