/*
 * Motor da DEMO pública (GitHub Pages) — módulo de compras.
 *
 * As páginas de docs/ são HTML estático gerado das views do sistema
 * (tests/Feature/GerarDemoTest.php). Este arquivo dá vida ao ciclo de
 * compras para quem está visitando a demo:
 *
 *   nova compra -> aparece na lista -> abrir -> lançar/editar/excluir
 *   entregas (com peso <-> sacas) -> classificar -> liquidar/reabrir
 *
 * REGRAS DA CASA
 *  - Tudo vive em sessionStorage: fecha a aba, acaba. É o que a demo
 *    promete a quem entra pelo link, e evita guardar dado de visitante.
 *  - As UTS de exemplo que já vêm na página são LEITURA. O que o visitante
 *    cria aparece marcado como "sua" e é totalmente editável.
 *  - A consulta de CNPJ não funciona (não há backend): o campo aceita
 *    digitação e o aviso explica isso, em vez de dar erro de rede.
 *  - Os cálculos repetem as regras do servidor: 60 kg/saca, saldo =
 *    contratado - entregue, lotes = sacas / 283,49, e "sem nº de lote não
 *    entra em estoque".
 */
(function () {
    'use strict';

    var CHAVE = 'ut-demo-compras';
    var KG_POR_SACA = 60;
    var SACAS_POR_LOTE = 283.49;

    var ARMAZENS = { SAAG: 'SAAG', QUALITE: 'QUALITÉ', DINAMO_MACHADO: 'DÍNAMO MACHADO' };

    var CERTIFICACOES = {
        SEM_CERT: 'Sem certificação', '4C': '4C', RFA: 'RFA', EUDR: 'EUDR',
        '4C_EUDR': '4C + EUDR', RFA_EUDR: 'RFA + EUDR', '4C_RFA': '4C + RFA'
    };

    var PADROES = {
        FINE_CUP: 'Fine Cup', GOOD_CUP: 'Good Cup', VERY_GOOD_CUP: 'Very Good Cup',
        GOOD_CUP_2R: 'Good Cup 2R', RIO_MINAS: 'Rio Minas',
        BICA_FINE_CUP: 'Bica Fine Cup', BICA_GOOD_CUP: 'Bica Good Cup',
        BICA_VERY_GOOD_CUP: 'Bica Very Good Cup'
    };

    var BEBIDAS = {
        DURO: 'Duro', DURO_1RY: 'Duro + 1RY', DURO_2RY: 'Duro + 2RY',
        DURO_2RY_1RIO: 'Duro + 2RY + 1 Rio', DURO_2RY_2RIO: 'Duro + 2RY + 2 Rio', RIO: 'Rio'
    };

    var FAIXAS = [
        ['peneira_12up', 'SCS 12 UP'], ['peneira_13up', 'SCS 13 UP'],
        ['peneira_1718', 'SCS 17/18'], ['peneira_1416', 'SCS 14/16'],
        ['mercado_interno', 'Mercado interno'], ['grinders', 'Grinders'], ['moka', 'Moka']
    ];

    /* ---------------- estado (sessionStorage) ---------------- */

    function ler() {
        try {
            return JSON.parse(sessionStorage.getItem(CHAVE)) || { compras: [], seq: 1 };
        } catch (e) {
            return { compras: [], seq: 1 };
        }
    }

    function gravar(estado) {
        try { sessionStorage.setItem(CHAVE, JSON.stringify(estado)); } catch (e) {}
    }

    function acharCompra(uts) {
        var achou = null;
        ler().compras.forEach(function (c) { if (c.uts === uts) achou = c; });
        return achou;
    }

    function salvarCompra(compra) {
        var estado = ler(), trocou = false;
        estado.compras = estado.compras.map(function (c) {
            if (c.uts === compra.uts) { trocou = true; return compra; }
            return c;
        });
        if (!trocou) estado.compras.unshift(compra);
        gravar(estado);
    }

    /* ---------------- formatação e contas ---------------- */

    function num(v, dec) {
        var n = parseFloat(v || 0);
        return n.toLocaleString('pt-BR', {
            minimumFractionDigits: dec === undefined ? 2 : dec,
            maximumFractionDigits: dec === undefined ? 2 : dec
        });
    }

    function dinheiro(v) { return 'R$ ' + num(v); }

    function dataBR(iso) {
        if (!iso) return '—';
        var p = String(iso).split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso;
    }

    function arredonda(n) { return Math.round(n * 100) / 100; }

    function entregues(compra) {
        return arredonda((compra.entregas || []).reduce(function (t, e) {
            return t + parseFloat(e.sacas || 0);
        }, 0));
    }

    function pesoEntregue(compra) {
        return arredonda((compra.entregas || []).reduce(function (t, e) {
            return t + parseFloat(e.peso || (e.sacas || 0) * KG_POR_SACA);
        }, 0));
    }

    function saldo(compra) { return arredonda(parseFloat(compra.sacas || 0) - entregues(compra)); }

    function liquidada(compra) { return !!compra.liquidadaEm; }

    function divergente(compra) { return !liquidada(compra) && Math.abs(saldo(compra)) > 0.01; }

    function semLote(compra) {
        return (compra.entregas || []).filter(function (e) { return !e.lote; }).length;
    }

    function valorEntregue(compra) {
        return compra.valorSaca ? arredonda(entregues(compra) * parseFloat(compra.valorSaca)) : null;
    }

    function hoje() { return new Date().toISOString().slice(0, 10); }

    function el(tag, attrs, texto) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) { node.setAttribute(k, attrs[k]); });
        if (texto !== undefined) node.textContent = texto;
        return node;
    }

    /* ---------------- aviso da demo ---------------- */

    function avisoDemo(mensagem) {
        var barra = document.querySelector('.demo-aviso');

        if (!barra) {
            barra = el('div', { class: 'demo-aviso alert' });
            barra.style.cssText = 'background:#E3EEE7;color:#123D2A;border:1px solid #BFE0C9;'
                + 'display:flex;gap:12px;align-items:baseline;flex-wrap:wrap';
            var conteudo = document.querySelector('.content');
            if (conteudo) conteudo.insertBefore(barra, conteudo.querySelector('.page-head').nextSibling);
        }

        barra.textContent = '';
        barra.appendChild(el('strong', {}, 'Demonstração:'));
        barra.appendChild(el('span', {}, mensagem));

        var limpar = el('button', { type: 'button', class: 'mini' }, 'Limpar meus dados');
        limpar.style.marginLeft = 'auto';
        limpar.addEventListener('click', function () {
            sessionStorage.removeItem(CHAVE);
            window.location.href = 'compras.html';
        });
        barra.appendChild(limpar);
    }

    /* =========================================================
     * 1. Nova compra (compra-nova.html)
     * ========================================================= */

    function telaNovaCompra() {
        var form = document.querySelector('form[action="compras.html"]');
        if (!form) return;

        avisoDemo('o que você cadastrar fica só neste navegador e desaparece quando a aba fechar.');

        // A consulta de CNPJ precisa de backend: explica em vez de falhar.
        var botao = document.getElementById('btnBuscarCnpj'),
            aviso = document.getElementById('avisoCnpj');

        if (botao && aviso) {
            botao.addEventListener('click', function (ev) {
                ev.stopImmediatePropagation();
                aviso.textContent = 'Na demonstração a busca por CNPJ não consulta a Receita — '
                    + 'digite o nome do vendedor (no sistema real o nome vem automático).';
            }, true);
        }

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            ev.stopImmediatePropagation();

            var v = function (nome) {
                var campo = form.querySelector('[name="' + nome + '"]');
                return campo ? campo.value.trim() : '';
            };

            var faltando = [];
            if (!v('uts')) faltando.push('UTS');
            if (!v('fornecedor_nome')) faltando.push('vendedor');
            if (!v('certificacao')) faltando.push('certificação');
            if (!v('volume_contratado') && !v('peso_kg')) faltando.push('volume (sacas ou peso)');

            if (faltando.length) {
                alert('Preencha: ' + faltando.join(', ') + '.');
                return;
            }

            if (acharCompra(v('uts'))) {
                alert('Já existe uma compra sua com a UTS ' + v('uts') + ' nesta demonstração.');
                return;
            }

            var conilon = v('tipo_entrada') === 'CONILON';
            var sacas = parseFloat(v('volume_contratado')) || arredonda(parseFloat(v('peso_kg')) / KG_POR_SACA);
            var peso = parseFloat(v('peso_kg')) || arredonda(sacas * KG_POR_SACA);

            salvarCompra({
                uts: v('uts'),
                data: v('data_compra') || hoje(),
                vendedor: v('fornecedor_nome'),
                documento: v('fornecedor_documento'),
                certificacao: v('certificacao'),
                tipo: conilon ? 'CONILON' : 'ARABICA',
                padrao: conilon ? '' : v('padrao_final'),
                bebida: conilon ? '' : v('tipo_bebida'),
                logistica: v('logistica'),
                sacas: sacas,
                peso: peso,
                valorSaca: parseFloat(v('valor_saca')) || null,
                corretor: v('corretor_nome'),
                comissao: v('comissao_pct'),
                pagamento: v('pagamento_previsto'),
                pagamentoObs: v('pagamento_obs'),
                entregas: [],
                classificacao: null,
                liquidadaEm: null
            });

            window.location.href = 'compras.html?nova=' + encodeURIComponent(v('uts'));
        }, true);
    }

    /* =========================================================
     * 2. Lista de compras (compras.html)
     * ========================================================= */

    function telaLista() {
        var tabela = document.querySelector('table.data--cards tbody');
        if (!tabela || !document.title.match(/Compras lan/)) return;

        var minhas = ler().compras;

        avisoDemo(minhas.length === 0
            ? 'clique em "Nova compra" para lançar uma UTS de verdade; ela vai aparecer aqui.'
            : (minhas.length === 1
                ? 'a primeira linha é a sua compra — clique em Abrir para lançar entregas e classificar.'
                : 'as ' + minhas.length + ' primeiras linhas são suas — clique em Abrir para lançar entregas e classificar.'));

        minhas.slice().reverse().forEach(function (compra) {
            tabela.insertBefore(linhaDaLista(compra), tabela.firstChild);
        });
    }

    function linhaDaLista(compra) {
        var tr = el('tr');
        tr.style.background = '#FBF7E9';

        var entr = entregues(compra), s = saldo(compra), faltaLote = semLote(compra);

        function td(rotulo, conteudo, classe) {
            var cel = el('td', { 'data-label': rotulo });
            if (classe) cel.className = classe;
            if (typeof conteudo === 'string') cel.textContent = conteudo;
            else if (conteudo) cel.appendChild(conteudo);
            return cel;
        }

        function badge(texto, tipo) {
            return el('span', { class: 'badge badge--' + tipo }, texto);
        }

        var uts = el('td', { 'data-label': 'UTS' });
        uts.appendChild(el('strong', {}, compra.uts));
        uts.appendChild(document.createElement('br'));
        uts.appendChild(badge('sua', 'green'));
        tr.appendChild(uts);

        tr.appendChild(td('Data', dataBR(compra.data)));

        var vendedor = el('td', { 'data-label': 'Vendedor' });
        vendedor.appendChild(document.createTextNode(compra.vendedor));
        if (!compra.documento) {
            vendedor.appendChild(document.createElement('br'));
            vendedor.appendChild(badge('CNPJ/CPF a confirmar', 'amber'));
        }
        tr.appendChild(vendedor);

        tr.appendChild(td('Café', compra.tipo === 'CONILON' ? 'Conilon' : 'Arábica'));
        tr.appendChild(td('Certificação', CERTIFICACOES[compra.certificacao] || compra.certificacao));
        tr.appendChild(td('Contratado (sc)', num(compra.sacas), 'num'));

        var celEntregue = el('td', { 'data-label': 'Entregue (sc)', class: 'num' });
        celEntregue.appendChild(document.createTextNode(num(entr)));
        if (liquidada(compra)) {
            celEntregue.appendChild(document.createElement('br'));
            celEntregue.appendChild(badge('liquidada', 'green'));
        } else if (s > 0.01) {
            celEntregue.appendChild(document.createElement('br'));
            celEntregue.appendChild(badge('faltam ' + num(s, 0), 'amber'));
        } else if (s < -0.01) {
            celEntregue.appendChild(document.createElement('br'));
            celEntregue.appendChild(badge('+' + num(Math.abs(s), 0) + ' a mais', 'amber'));
        }
        tr.appendChild(celEntregue);

        tr.appendChild(td('Entregue (kg)', compra.entregas.length ? num(pesoEntregue(compra)) : '—', 'num'));

        var celEntregas = el('td', { 'data-label': 'Entregas' });
        if (!compra.entregas.length) {
            celEntregas.appendChild(badge('nenhuma', 'muted'));
        } else {
            var armazens = [];
            compra.entregas.forEach(function (e) {
                var nome = ARMAZENS[e.armazem] || e.armazem;
                if (armazens.indexOf(nome) === -1) armazens.push(nome);
            });
            celEntregas.appendChild(document.createTextNode(compra.entregas.length + ' (' + armazens.join(', ') + ')'));
            if (faltaLote) {
                celEntregas.appendChild(document.createElement('br'));
                celEntregas.appendChild(badge('⚠ ' + faltaLote + ' sem lote', 'red'));
            }
        }
        tr.appendChild(celEntregas);

        var celPadrao = el('td', { 'data-label': 'Padrão' });
        if (compra.tipo === 'CONILON') {
            celPadrao.textContent = '—';
        } else if (compra.classificacao) {
            celPadrao.appendChild(badge(PADROES[compra.classificacao.padrao] || compra.classificacao.padrao, 'green'));
        } else if (compra.padrao) {
            celPadrao.appendChild(badge(PADROES[compra.padrao] || compra.padrao, 'muted'));
        } else {
            celPadrao.appendChild(badge('Não classificada', 'muted'));
        }
        tr.appendChild(celPadrao);

        var acao = el('td', { class: 'cell-action' });
        var abrir = el('a', {
            href: 'compra.html?uts=' + encodeURIComponent(compra.uts),
            class: 'btn btn-ghost'
        }, 'Abrir');
        abrir.style.cssText = 'padding:6px 12px; font-size:13px;';
        acao.appendChild(abrir);
        tr.appendChild(acao);

        return tr;
    }

    /* =========================================================
     * 3. Tela da compra (compra.html?uts=...)
     * ========================================================= */

    function utsDaUrl() {
        var m = window.location.search.match(/[?&]uts=([^&]*)/);
        return m ? decodeURIComponent(m[1]) : null;
    }

    function telaDaCompra() {
        if (!document.title.match(/^Compra /)) return;

        var uts = utsDaUrl();
        if (!uts) {
            avisoDemo('esta é uma UTS de exemplo (somente leitura). Cadastre uma compra sua em "Nova compra" para editar entregas e classificar.');
            return;
        }

        var compra = acharCompra(uts);
        if (!compra) {
            avisoDemo('não encontrei a UTS ' + uts + ' nesta sessão — ela pode ter sido limpa ao fechar a aba.');
            return;
        }

        document.title = 'Compra ' + uts;
        avisoDemo('esta é a SUA ' + uts + ' — lance entregas, classifique e liquide como no sistema real.');

        pintarCabecalho(compra);
        pintarAlertas(compra);
        pintarDados(compra);
        pintarEntregas(compra);
        pintarClassificacao(compra);
        pintarFinanceiro(compra);
    }

    function pintarCabecalho(compra) {
        var h1 = document.querySelector('.page-head h1');
        if (h1) h1.textContent = 'Compra ' + compra.uts;

        var sub = document.querySelector('.page-head .page-sub');
        if (sub) sub.textContent = compra.vendedor + ' · ' + dataBR(compra.data);

        var crumb = document.querySelector('.appbar__crumb b');
        if (crumb) crumb.textContent = compra.uts;

        var itens = document.querySelectorAll('.calc-grid .calc-item');
        if (itens.length < 4) return;

        var entr = entregues(compra), s = saldo(compra);

        function preencher(item, rotulo, valor, alerta) {
            item.querySelector('.calc-lbl').textContent = rotulo;
            var val = item.querySelector('.calc-val');
            val.textContent = valor;
            val.className = 'calc-val' + (alerta ? ' is-alerta' : '');
        }

        preencher(itens[0], 'Contratado', num(compra.sacas) + ' sc', false);
        preencher(itens[1], 'Entregue', num(entr) + ' sc', false);
        preencher(
            itens[2],
            liquidada(compra) ? 'Liquidada' : (s < 0 ? 'Entregue a mais' : 'Falta entregar'),
            (liquidada(compra) ? num(entr) : num(Math.abs(s))) + ' sc',
            divergente(compra)
        );
        preencher(itens[3], 'Valor efetivo', valorEntregue(compra) === null ? '—' : dinheiro(valorEntregue(compra)), false);
    }

    /** Avisos de liquidação/divergência/lote, redesenhados do estado. */
    function pintarAlertas(compra) {
        document.querySelectorAll('.demo-alerta').forEach(function (n) { n.remove(); });

        // Os alertas que vieram no HTML são da UTS de exemplo.
        var grid = document.querySelector('.calc-grid');
        var irmao = grid ? grid.nextElementSibling : null;
        while (irmao && irmao.classList && irmao.classList.contains('alert')) {
            var proximo = irmao.nextElementSibling;
            irmao.remove();
            irmao = proximo;
        }

        var entr = entregues(compra), s = saldo(compra), depois = grid;

        function inserir(node) {
            node.classList.add('demo-alerta');
            depois.parentNode.insertBefore(node, depois.nextSibling);
            depois = node;
        }

        if (liquidada(compra)) {
            var okBox = el('div', { class: 'alert alert-success' });
            okBox.style.cssText = 'display:flex;align-items:baseline;gap:10px;flex-wrap:wrap';
            okBox.appendChild(el('strong', {}, 'Compra liquidada com ' + num(entr) + ' sc.'));
            okBox.appendChild(el('span', {}, 'Encerrada em ' + dataBR(compra.liquidadaEm)
                + ' — o sistema reconhece este volume como final'
                + (Math.abs(s) > 0.01 ? ' (contratado era ' + num(compra.sacas) + ' sc)' : '') + '.'));
            var reabrir = el('button', { type: 'button', class: 'mini' }, 'Reabrir');
            reabrir.style.marginLeft = 'auto';
            reabrir.addEventListener('click', function () {
                compra.liquidadaEm = null;
                salvarCompra(compra);
                window.location.reload();
            });
            okBox.appendChild(reabrir);
            inserir(okBox);
        } else if (divergente(compra) && compra.entregas.length) {
            var box = el('div', { class: 'alert' });
            box.style.cssText = 'background:#FCF3DC;color:#8A6116;border:1px solid #EBD9A8;'
                + 'display:flex;align-items:baseline;gap:10px;flex-wrap:wrap';
            box.appendChild(el('strong', {}, s > 0
                ? 'Faltam ' + num(s) + ' sc para completar o contratado.'
                : 'Entraram ' + num(Math.abs(s)) + ' sc a mais que o contratado.'));
            box.appendChild(el('span', {}, 'Se não vem (nem sai) mais nada, liquide a compra: o sistema passa a '
                + 'reconhecer as ' + num(entr) + ' sc entregues como o volume final.'));
            var liquidar = el('button', { type: 'button', class: 'btn btn-primary' }, 'Liquidar compra');
            liquidar.style.cssText = 'margin-left:auto;padding:6px 14px;font-size:13px';
            liquidar.addEventListener('click', function () {
                if (!confirm('Liquidar a ' + compra.uts + ' com ' + num(entr) + ' sc?')) return;
                compra.liquidadaEm = hoje();
                salvarCompra(compra);
                window.location.reload();
            });
            box.appendChild(liquidar);
            inserir(box);
        }

        var faltaLote = semLote(compra);
        if (faltaLote) {
            var lote = el('div', { class: 'alert alert-error' });
            lote.appendChild(el('strong', {}, faltaLote + (faltaLote > 1 ? ' entregas' : ' entrega') + ' sem número de lote.'));
            lote.appendChild(el('span', { class: 'alert__hint' },
                'Enquanto o armazém não informar o lote, esse café não conta como estoque definitivo.'));
            inserir(lote);
        }
    }

    function pintarDados(compra) {
        var campos = document.querySelectorAll('.card .form-grid .field');

        campos.forEach(function (campo) {
            var rotulo = campo.querySelector('label');
            var valor = campo.querySelector('div');
            if (!rotulo || !valor) return;

            var texto = rotulo.textContent.trim();

            if (texto === 'UTS') valor.textContent = compra.uts;
            else if (texto === 'Data da compra') valor.textContent = dataBR(compra.data);
            else if (texto === 'Vendedor') valor.textContent = compra.vendedor;
            else if (texto === 'CNPJ / CPF') valor.textContent = compra.documento || 'a confirmar';
            else if (texto === 'Certificação') valor.textContent = CERTIFICACOES[compra.certificacao] || compra.certificacao;
            else if (texto === 'Logística') valor.textContent = compra.logistica === 'RETIRAR' ? 'Retirar' : (compra.logistica === 'POSTO' ? 'Posto' : '—');
            else if (texto === 'Tipo de café') valor.textContent = compra.tipo === 'CONILON' ? 'Conilon' : 'Arábica';
            else if (texto === 'Volume contratado') valor.textContent = num(compra.sacas) + ' sacas · ' + num(compra.peso) + ' kg';
            else if (texto === 'Padrão final') valor.textContent = PADROES[compra.padrao] || '—';
            else if (texto === 'Tipo de bebida') valor.textContent = BEBIDAS[compra.bebida] || '—';
        });
    }

    /* ---- entregas: tabela editável ---- */

    function pintarEntregas(compra) {
        var tabela = document.querySelector('table.tabela-entregas');
        if (!tabela) return;

        var corpo = tabela.querySelector('tbody');
        corpo.textContent = '';

        if (!compra.entregas.length) {
            var vazio = el('tr');
            var cel = el('td', { colspan: '8' }, 'Nenhuma entrega lançada — o café desta UTS ainda não entrou no armazém.');
            cel.style.cssText = 'text-align:center;color:var(--muted);padding:24px';
            vazio.appendChild(cel);
            corpo.appendChild(vazio);
        } else {
            compra.entregas.slice().sort(function (a, b) {
                return a.data < b.data ? -1 : 1;
            }).forEach(function (entrega) {
                corpo.appendChild(linhaDeEntrega(compra, entrega));
            });
        }

        var rodape = tabela.querySelector('tfoot');
        if (rodape) rodape.remove();

        if (compra.entregas.length > 1) {
            var tfoot = el('tfoot'), tr = el('tr');
            tr.appendChild(el('td', { colspan: '2' }, 'Total entregue'));
            tr.appendChild(el('td', { class: 'num' }, num(entregues(compra))));
            tr.appendChild(el('td', { class: 'num' }, num(pesoEntregue(compra))));
            tr.appendChild(el('td', { class: 'num' }, valorEntregue(compra) === null ? '—' : dinheiro(valorEntregue(compra))));
            tr.appendChild(el('td', { colspan: '3' }));
            tfoot.appendChild(tr);
            tabela.appendChild(tfoot);
        }

        prepararFormularioDeEntrega(compra);
    }

    function linhaDeEntrega(compra, entrega) {
        var tr = el('tr');

        var data = el('input', { type: 'date', value: entrega.data });
        tr.appendChild(celula(data));

        var armazem = el('select');
        Object.keys(ARMAZENS).forEach(function (cod) {
            var opt = el('option', { value: cod }, ARMAZENS[cod]);
            if (entrega.armazem === cod) opt.selected = true;
            armazem.appendChild(opt);
        });
        tr.appendChild(celula(armazem));

        var sacas = el('input', { type: 'number', step: '0.01', min: '0.01', value: entrega.sacas, class: 'campo-sacas js-sacas' });
        tr.appendChild(celula(sacas, 'num'));

        var peso = el('input', { type: 'number', step: '0.01', min: '0.01', value: entrega.peso, class: 'campo-peso js-peso' });
        tr.appendChild(celula(peso, 'num'));

        var valor = el('td', { class: 'num' }, compra.valorSaca
            ? dinheiro(arredonda(parseFloat(entrega.sacas) * parseFloat(compra.valorSaca)))
            : '—');
        valor.style.cssText = 'font-family:var(--font-data);font-size:13px';
        tr.appendChild(valor);

        var lote = el('input', { type: 'text', value: entrega.lote || '', placeholder: 'Ex.: L-2026-0451', class: 'campo-lote' });
        tr.appendChild(celula(lote));

        var quem = el('td', {}, 'você');
        quem.style.cssText = 'font-size:12.5px;color:var(--muted)';
        tr.appendChild(quem);

        var acoes = el('td', { class: 'cell-action' });
        acoes.style.cssText = 'display:flex;gap:6px;justify-content:flex-end';

        var salvar = el('button', { type: 'button', class: 'mini' }, 'Salvar');
        salvar.addEventListener('click', function () {
            entrega.data = data.value;
            entrega.armazem = armazem.value;
            entrega.sacas = parseFloat(sacas.value) || 0;
            entrega.peso = parseFloat(peso.value) || arredonda(entrega.sacas * KG_POR_SACA);
            entrega.lote = lote.value.trim();
            salvarCompra(compra);
            window.location.reload();
        });
        acoes.appendChild(salvar);

        var excluir = el('button', { type: 'button', class: 'mini mini--danger' }, 'Excluir');
        excluir.addEventListener('click', function () {
            if (!confirm('Remover esta entrega? O saldo da UTS será recalculado.')) return;
            compra.entregas = compra.entregas.filter(function (e) { return e !== entrega; });
            salvarCompra(compra);
            window.location.reload();
        });
        acoes.appendChild(excluir);

        tr.appendChild(acoes);

        return tr;
    }

    function celula(campo, classe) {
        var td = el('td');
        if (classe) td.className = classe;
        td.appendChild(campo);
        return td;
    }

    function prepararFormularioDeEntrega(compra) {
        var form = document.querySelector('form[action="#"]');
        if (!form || !form.querySelector('[name="volume_sacas"]')) return;

        var s = saldo(compra);
        var campoSacas = form.querySelector('[name="volume_sacas"]'),
            campoPeso = form.querySelector('[name="peso_kg"]'),
            campoData = form.querySelector('[name="data_entrega"]');

        if (campoData) campoData.value = hoje();
        if (campoSacas) campoSacas.value = s > 0 ? s : '';
        if (campoPeso) campoPeso.value = s > 0 ? arredonda(s * KG_POR_SACA) : '';

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            ev.stopImmediatePropagation();

            var sacas = parseFloat(campoSacas.value) || 0;
            var peso = parseFloat(campoPeso.value) || 0;

            if (!sacas && !peso) {
                alert('Informe as sacas ou o peso que entraram no armazém.');
                return;
            }
            if (!sacas) sacas = arredonda(peso / KG_POR_SACA);
            if (!peso) peso = arredonda(sacas * KG_POR_SACA);

            compra.entregas.push({
                data: campoData.value || hoje(),
                armazem: form.querySelector('[name="armazem"]').value,
                sacas: sacas,
                peso: peso,
                lote: (form.querySelector('[name="numero_lote"]').value || '').trim()
            });

            salvarCompra(compra);
            window.location.reload();
        }, true);
    }

    /* ---- classificação (card da tela da compra) ---- */

    function pintarClassificacao(compra) {
        var cards = document.querySelectorAll('.card');
        var card = null;

        cards.forEach(function (c) {
            var h2 = c.querySelector('.card__header h2');
            if (h2 && h2.textContent.indexOf('Seleção e classificação') > -1) card = c;
        });

        if (!card) return;

        var link = card.querySelector('a');
        if (link) {
            link.setAttribute('href', 'compra-classificacao.html?uts=' + encodeURIComponent(compra.uts));
            link.textContent = compra.classificacao ? 'Editar' : 'Classificar';
        }

        var corpo = card.querySelector('.card__body');
        corpo.textContent = '';

        if (!compra.classificacao) {
            corpo.appendChild(el('p', {}, 'Esta compra ainda não foi classificada.'));
            corpo.querySelector('p').style.cssText = 'color:var(--muted);margin:0';
            return;
        }

        var c = compra.classificacao;
        var wrap = el('div', { class: 'table-wrap' }), tabela = el('table', { class: 'data' });
        var thead = el('thead'), trh = el('tr');

        trh.appendChild(el('th', {}, 'Padrão final'));
        trh.appendChild(el('th', {}, 'Tipo de bebida'));
        FAIXAS.forEach(function (f) { trh.appendChild(el('th', { class: 'num' }, f[1])); });
        trh.appendChild(el('th', { class: 'num' }, 'Qtd. lotes'));
        thead.appendChild(trh);
        tabela.appendChild(thead);

        var tbody = el('tbody'), tr = el('tr'), total = 0;
        tr.appendChild(el('td', {}, PADROES[c.padrao] || '—'));
        tr.appendChild(el('td', {}, BEBIDAS[c.bebida] || '—'));

        FAIXAS.forEach(function (f) {
            var sacas = parseFloat((c.faixas[f[0]] || {}).sacas || 0);
            var pct = parseFloat((c.faixas[f[0]] || {}).pct || 0);
            total += sacas;
            tr.appendChild(el('td', { class: 'num' }, num(sacas) + ' (' + num(pct, 1) + '%)'));
        });

        tr.appendChild(el('td', { class: 'num' }, num(total / SACAS_POR_LOTE, 4)));
        tbody.appendChild(tr);
        tabela.appendChild(tbody);
        wrap.appendChild(tabela);
        corpo.appendChild(wrap);
    }

    function pintarFinanceiro(compra) {
        var cards = document.querySelectorAll('.card');
        var card = null;

        cards.forEach(function (c) {
            var h2 = c.querySelector('.card__header h2');
            if (h2 && h2.textContent.trim() === 'Financeiro') card = c;
        });

        if (!card) return;

        var link = card.querySelector('a');
        if (link) link.setAttribute('href', '#');

        var corpo = card.querySelector('.card__body');
        corpo.textContent = '';

        if (!compra.valorSaca) {
            var p = el('p', {}, 'Preço ainda não lançado.');
            p.style.cssText = 'color:var(--muted);margin:0';
            corpo.appendChild(p);
            return;
        }

        var grid = el('div', { class: 'form-grid' });

        function campo(rotulo, valor) {
            var f = el('div', { class: 'field' });
            f.appendChild(el('label', {}, rotulo));
            f.appendChild(el('div', {}, valor));
            grid.appendChild(f);
        }

        campo('Valor da saca', dinheiro(compra.valorSaca));
        campo('Valor contratado', dinheiro(arredonda(compra.sacas * compra.valorSaca)));
        campo('Valor efetivo (entregue)', valorEntregue(compra) === null ? '—' : dinheiro(valorEntregue(compra)));
        campo('Corretor', compra.corretor || '—');
        campo('Comissão', compra.comissao ? num(compra.comissao) + '%' : '—');
        campo('Pagamento', compra.pagamento ? dataBR(compra.pagamento) : '—');

        corpo.appendChild(grid);
    }

    /* =========================================================
     * 4. Classificação (compra-classificacao.html?uts=...)
     * ========================================================= */

    function telaClassificacao() {
        if (!document.title.match(/^Classifica/)) return;

        var uts = utsDaUrl();
        var compra = uts ? acharCompra(uts) : null;
        var form = document.querySelector('#form-classificacao');
        if (!form) return;

        if (!compra) {
            avisoDemo('exemplo somente leitura. Abra uma compra sua em "Compras lançadas" e clique em Classificar.');
            return;
        }

        document.title = 'Classificação — ' + compra.uts;
        avisoDemo('classificando a SUA ' + compra.uts + ' — a soma das porcentagens precisa fechar 100%.');

        var titulo = document.querySelector('.card__header h2');
        if (titulo) titulo.textContent = 'Classificação da compra ' + compra.uts;

        // Base do cálculo automático de sacas: o mesmo teto do servidor.
        var base = Math.max(parseFloat(compra.sacas), entregues(compra));
        var resumo = document.querySelector('.card__body > p');
        if (resumo) {
            resumo.textContent = 'Volume da UTS: ' + num(base) + ' sacas (contratado ' + num(compra.sacas)
                + ' · entregue ' + num(entregues(compra)) + '). Preencha a % de cada peneira — '
                + 'as sacas são calculadas, e a soma das % deve fechar 100%.';
        }

        // Conilon não tem padrão/bebida: some com o par de selects.
        if (compra.tipo === 'CONILON') {
            var padraoSel = form.querySelector('[name="padrao_final"]');
            if (padraoSel) {
                var blocoPadrao = padraoSel.closest('div').parentNode;
                blocoPadrao.style.display = 'none';
            }
        } else {
            var p = form.querySelector('[name="padrao_final"]'), b = form.querySelector('[name="tipo_bebida"]');
            if (p) p.value = (compra.classificacao && compra.classificacao.padrao) || compra.padrao || '';
            if (b) b.value = (compra.classificacao && compra.classificacao.bebida) || compra.bebida || '';
        }

        // Os campos vêm com a distribuição da UTS de EXEMPLO impressa no
        // HTML: zera tudo (ou traz o que o visitante já lançou), senão ele
        // classificaria a compra dele com números de outra.
        FAIXAS.forEach(function (f) {
            var dados = (compra.classificacao && compra.classificacao.faixas[f[0]]) || { pct: 0, sacas: 0 };
            var pct = form.querySelector('[name="' + f[0] + '_pct"]');
            var sacas = form.querySelector('[name="' + f[0] + '_sacas"]');
            if (pct) pct.value = dados.pct;
            if (sacas) sacas.value = dados.sacas;
        });

        // O cálculo automático de sacas da página usa o volume da UTS de
        // exemplo (constante no HTML gerado). Aqui ele é refeito sobre o
        // volume da compra do visitante — este listener entra DEPOIS do da
        // página, então é o último a escrever no campo.
        function recalcular() {
            var somaPct = 0, somaSacas = 0;

            FAIXAS.forEach(function (f) {
                var pctEl = form.querySelector('[name="' + f[0] + '_pct"]');
                var sacasEl = form.querySelector('[name="' + f[0] + '_sacas"]');
                if (!pctEl || !sacasEl) return;

                var pct = parseFloat(pctEl.value) || 0;
                sacasEl.value = arredonda(pct / 100 * base).toFixed(2);
                somaPct += pct;
                somaSacas += parseFloat(sacasEl.value) || 0;
            });

            var elPct = document.getElementById('soma-pct'),
                elSacas = document.getElementById('soma-sacas');
            if (elPct) elPct.textContent = somaPct.toFixed(2);
            if (elSacas) elSacas.textContent = somaSacas.toFixed(2);
        }

        form.querySelectorAll('.js-pct').forEach(function (campo) {
            campo.addEventListener('input', recalcular);
        });

        recalcular();

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            ev.stopImmediatePropagation();

            var faixas = {}, somaPct = 0, somaSacas = 0;

            FAIXAS.forEach(function (f) {
                var pct = parseFloat((form.querySelector('[name="' + f[0] + '_pct"]') || {}).value) || 0;
                var sacas = parseFloat((form.querySelector('[name="' + f[0] + '_sacas"]') || {}).value) || 0;
                faixas[f[0]] = { pct: pct, sacas: sacas };
                somaPct += pct;
                somaSacas += sacas;
            });

            if (Math.abs(somaPct - 100) > 0.5) {
                alert('A soma das porcentagens deve totalizar 100% (atual: ' + num(somaPct) + '%).');
                return;
            }
            if (somaSacas - base > 0.01) {
                alert('A soma das sacas (' + num(somaSacas) + ') não pode passar do volume da UTS (' + num(base) + ').');
                return;
            }

            var padraoEl = form.querySelector('[name="padrao_final"]'),
                bebidaEl = form.querySelector('[name="tipo_bebida"]');

            compra.classificacao = {
                padrao: compra.tipo === 'CONILON' ? '' : (padraoEl ? padraoEl.value : ''),
                bebida: compra.tipo === 'CONILON' ? '' : (bebidaEl ? bebidaEl.value : ''),
                faixas: faixas
            };

            // A conferência é a palavra final sobre a qualidade, como no app.
            if (compra.tipo !== 'CONILON') {
                compra.padrao = compra.classificacao.padrao;
                compra.bebida = compra.classificacao.bebida;
            }

            salvarCompra(compra);
            window.location.href = 'compra.html?uts=' + encodeURIComponent(compra.uts);
        }, true);

        // "Cancelar" volta para a compra certa.
        form.parentNode.querySelectorAll('a.btn-ghost').forEach(function (a) {
            a.setAttribute('href', 'compra.html?uts=' + encodeURIComponent(compra.uts));
        });
    }

    /* ---------------- início ---------------- */

    document.addEventListener('DOMContentLoaded', function () {
        try {
            telaNovaCompra();
            telaLista();
            telaDaCompra();
            telaClassificacao();
        } catch (e) {
            // Demo não pode quebrar a página: no pior caso ela volta a ser
            // estática, com os exemplos do HTML.
            if (window.console) console.error('demo-compras:', e);
        }
    });
})();
