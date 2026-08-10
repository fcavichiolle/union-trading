@extends('layouts.app')

@section('title', 'Cotações')
@section('crumb')
    <span>Mercado</span><span class="sep">/</span><b>Cotações</b>
@endsection
@section('subtitle')
    Café arábica (ICE NY), robusta (ICE Londres) e câmbio — via Yahoo Finance, com
    atraso de ~15 minutos. A página atualiza sozinha a cada 30 segundos.
@endsection

@section('content')
    <div class="mkt-meta">
        <span id="mktStatus" class="mkt-status">Carregando…</span>
        <span id="mktUpdated" style="color:var(--muted); font-size:12.5px;"></span>
    </div>

    <div class="mkt-cambio">
        <div class="card mkt-tile">
            <div class="mkt-tile__lbl">Dólar comercial</div>
            <div class="mkt-tile__val" data-fx="dolar">—</div>
            <div class="mkt-tile__dif" data-fx-dif="dolar"></div>
        </div>
        <div class="card mkt-tile">
            <div class="mkt-tile__lbl">Euro comercial</div>
            <div class="mkt-tile__val" data-fx="euro">—</div>
            <div class="mkt-tile__dif" data-fx-dif="euro"></div>
        </div>
    </div>

    <div class="contract-cols">
        <div class="card">
            <div class="card__header card__header--dark mesh-texture">
                <h2>Arábica — ICE New York (cts/lb)</h2>
            </div>
            <div class="card__body" style="padding:0;">
                <div class="table-wrap" style="border:0; border-radius:0;">
                    <table class="data mkt-table" id="tblArabica" data-dec="2">
                        <thead>
                            <tr>
                                <th>Posição</th>
                                <th class="num">Último</th>
                                <th class="num">Dif.</th>
                                <th class="num">Máx.</th>
                                <th class="num">Mín.</th>
                                <th class="num">Abert.</th>
                                <th class="num">Fech. ant.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (\App\Services\MercadoCafe::ARABICA as $p)
                                <tr data-code="{{ $p['code'] }}">
                                    <td><strong>{{ $p['code'] }}</strong> <span style="color:var(--muted); font-size:12px;">{{ $p['month'] }}</span> <span class="mkt-flag"></span></td>
                                    <td class="num" data-f="price">—</td>
                                    <td class="num" data-f="dif">—</td>
                                    <td class="num" data-f="max">—</td>
                                    <td class="num" data-f="min">—</td>
                                    <td class="num" data-f="open">—</td>
                                    <td class="num" data-f="close">—</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card__header card__header--dark mesh-texture">
                <h2>Robusta — ICE Londres (USD/ton)</h2>
            </div>
            <div class="card__body" style="padding:0;">
                <div class="table-wrap" style="border:0; border-radius:0;">
                    <table class="data mkt-table" id="tblRobusta" data-dec="0">
                        <thead>
                            <tr>
                                <th>Posição</th>
                                <th class="num">Último</th>
                                <th class="num">Dif.</th>
                                <th class="num">Máx.</th>
                                <th class="num">Mín.</th>
                                <th class="num">Abert.</th>
                                <th class="num">Fech. ant.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (\App\Services\MercadoCafe::ROBUSTA as $p)
                                <tr data-code="{{ $p['code'] }}">
                                    <td><strong>{{ $p['code'] }}</strong> <span style="color:var(--muted); font-size:12px;">{{ $p['month'] }}</span> <span class="mkt-flag"></span></td>
                                    <td class="num" data-f="price">—</td>
                                    <td class="num" data-f="dif">—</td>
                                    <td class="num" data-f="max">—</td>
                                    <td class="num" data-f="min">—</td>
                                    <td class="num" data-f="open">—</td>
                                    <td class="num" data-f="close">—</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <p style="color:var(--muted); font-size:12.5px; margin-top:14px;">
        Posições sem cobertura no Yahoo Finance aparecem como <em>indisponível</em>; valores marcados
        com ⏱ são o último valor conhecido (a fonte não respondeu na atualização mais recente).
    </p>

<script>
    (function () {
        var INICIAL = @json($snapshot);
        var ultimoBom = null;

        function fmt(v, dec) {
            return v == null ? '—' : Number(v).toLocaleString('pt-BR', { minimumFractionDigits: dec, maximumFractionDigits: dec });
        }

        function pintarDif(el, v, dec) {
            if (v == null) { el.textContent = '—'; el.className = 'num'; return; }
            el.textContent = (v >= 0 ? '+' : '') + fmt(v, dec);
            el.className = 'num ' + (v >= 0 ? 'mkt-up' : 'mkt-down');
        }

        function pintarTabela(tabela, linhas) {
            var dec = parseInt(tabela.getAttribute('data-dec'), 10);
            (linhas || []).forEach(function (item) {
                var tr = tabela.querySelector('tr[data-code="' + item.code + '"]');
                if (!tr) return;
                var flag = tr.querySelector('.mkt-flag');
                if (item.price == null) {
                    tr.classList.add('mkt-indisp');
                    flag.textContent = 'indisponível';
                    return;
                }
                tr.classList.remove('mkt-indisp');
                flag.textContent = item.stale ? '⏱' : '';
                flag.title = item.stale ? 'Último valor conhecido — a fonte não respondeu agora.' : '';
                ['price', 'max', 'min', 'open', 'close'].forEach(function (campo) {
                    tr.querySelector('[data-f="' + campo + '"]').textContent = fmt(item[campo], dec);
                });
                pintarDif(tr.querySelector('[data-f="dif"]'), item.dif, dec);
            });
        }

        function pintar(m, aoVivo) {
            pintarTabela(document.getElementById('tblArabica'), m.arabica);
            pintarTabela(document.getElementById('tblRobusta'), m.robusta);

            ['dolar', 'euro'].forEach(function (chave) {
                var fx = (m.cambio || {})[chave] || {};
                document.querySelector('[data-fx="' + chave + '"]').textContent =
                    fx.value == null ? 'indisponível' : 'R$ ' + fmt(fx.value, 4);
                pintarDif(document.querySelector('[data-fx-dif="' + chave + '"]'), fx.dif, 4);
            });

            var st = document.getElementById('mktStatus');
            st.textContent = aoVivo ? '● ao vivo (delay ~15 min)' : '● sem conexão — mostrando últimos valores';
            st.className = 'mkt-status ' + (aoVivo ? 'mkt-status--ok' : 'mkt-status--off');
            if (m.updated_at) {
                document.getElementById('mktUpdated').textContent =
                    'Atualizado às ' + new Date(m.updated_at).toLocaleTimeString('pt-BR');
            }
        }

        function atualizar() {
            fetch('{{ route('mercado.api') }}', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
                .then(function (m) { ultimoBom = m; pintar(m, true); })
                .catch(function () { if (ultimoBom) pintar(ultimoBom, false); else pintar(INICIAL, false); });
        }

        ultimoBom = INICIAL;
        pintar(INICIAL, true);
        setInterval(atualizar, 30000);
    })();
</script>
@endsection
