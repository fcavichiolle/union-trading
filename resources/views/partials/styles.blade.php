<style>
  :root {
    --bg: #F6F2EA;
    --surface: #FFFFFF;
    --primary: #123D2A;
    --primary-dark: #0B2A1C;
    --primary-light: #1C5C3E;
    --primary-soft: #E3EEE7;
    --accent: #B03A2E;
    --accent-dark: #8E2E24;
    --ink: #212A24;
    --muted: #6B7570;
    --border: #E2DCCB;
    --danger-bg: #FBE9E7;
    --danger-text: #8E2E24;
    --success-bg: #E7F3EA;
    --success-text: #1C5C3E;
    --radius: 10px;
    --radius-sm: 6px;
    --shadow: 0 1px 2px rgba(18, 61, 42, .06), 0 6px 20px rgba(18, 61, 42, .06);
    --font-display: Georgia, 'Iowan Old Style', 'Palatino Linotype', 'Times New Roman', serif;
    --font-body: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    --font-data: 'SF Mono', SFMono-Regular, Consolas, 'Liberation Mono', Menlo, monospace;
  }

  * { box-sizing: border-box; }
  html, body { height: 100%; }
  body {
    margin: 0;
    font-family: var(--font-body);
    background: var(--bg);
    color: var(--ink);
    -webkit-font-smoothing: antialiased;
  }
  a { color: inherit; }
  h1, h2, h3 { font-family: var(--font-display); font-weight: 700; margin: 0 0 .4em; letter-spacing: .01em; }
  p { line-height: 1.55; }

  :focus-visible {
    outline: 3px solid var(--accent);
    outline-offset: 2px;
  }

  /* ---------- Sieve/peneira dot-mesh texture (assinatura visual) ---------- */
  .mesh-texture {
    background-image: radial-gradient(rgba(255,255,255,.16) 1px, transparent 1px);
    background-size: 9px 9px;
  }

  /* ---------- App shell (sidebar verde + header) ---------- */
  .app-shell { display: flex; min-height: 100vh; }

  .sidebar {
    width: 272px; flex-shrink: 0;
    display: flex; flex-direction: column;
    background: linear-gradient(178deg, #0C4028 0%, #093020 100%);
    box-shadow: inset -1px 0 0 rgba(255,255,255,.06);
    color: #E2F0E8;
  }
  .sidebar__logo {
    height: 132px; flex: none; background: #FAF6EC;
    display: flex; align-items: center; justify-content: center; padding: 16px 0;
    box-shadow: inset 0 -1px 0 rgba(11,61,36,.10);
  }
  .sidebar__logo img { height: 100px; width: auto; display: block; }

  .sidebar__nav { flex: 1; display: flex; flex-direction: column; gap: 22px; padding: 22px 16px 0; overflow-y: auto; }
  .sb-group { display: flex; flex-direction: column; gap: 4px; }
  .sb-group__label {
    padding: 0 14px 8px; color: rgba(220,238,228,.52);
    font-family: var(--font-data); font-size: 10px; font-weight: 600; letter-spacing: .13em; text-transform: uppercase;
  }
  .sb-link {
    position: relative; display: flex; align-items: center; gap: 12px;
    padding: 11px 14px; border-radius: 10px;
    color: rgba(226,240,232,.82); text-decoration: none; font-size: 13.5px;
  }
  .sb-link svg { flex: none; opacity: .8; }
  .sb-link:hover { background: rgba(255,255,255,.07); color: #fff; }
  .sb-link:hover svg { opacity: 1; }
  .sb-link.is-active {
    color: #fff; font-weight: 600;
    background: linear-gradient(180deg, rgba(255,255,255,.16), rgba(255,255,255,.07));
    box-shadow: inset 0 1px 0 rgba(255,255,255,.28), 0 1px 2px rgba(0,0,0,.25), 0 0 22px -6px rgba(122,214,163,.55);
  }
  .sb-link.is-active svg { opacity: 1; }
  .sb-link.is-active::before {
    content: ""; position: absolute; left: 0; top: 11px; bottom: 11px;
    width: 3px; border-radius: 0 3px 3px 0; background: #E8322C;
  }

  .sidebar__foot {
    padding: 16px 22px; display: flex; align-items: center; gap: 11px;
    color: rgba(220,238,228,.55); font-size: 11px; box-shadow: inset 0 1px 0 rgba(255,255,255,.07);
  }
  .sidebar__foot .dot { width: 7px; height: 7px; border-radius: 50%; background: #7AD6A3; box-shadow: 0 0 8px rgba(122,214,163,.9); flex: none; }
  .sidebar__foot form { margin: 0; margin-left: auto; }
  .sidebar__foot a, .sidebar__foot button {
    color: rgba(220,238,228,.7); background: none; border: 0; padding: 0; cursor: pointer;
    font: inherit; font-size: 11px; text-decoration: underline;
  }
  .sidebar__foot a:hover, .sidebar__foot button:hover { color: #fff; }

  .main {
    flex: 1; min-width: 0; display: flex; flex-direction: column;
    background: radial-gradient(120% 80% at 20% 0%, #FDFBF4 0%, #F6F1E4 60%, #F2ECDD 100%);
  }
  .appbar {
    height: 72px; flex: none; display: flex; align-items: center; justify-content: space-between;
    gap: 20px; padding: 0 32px; box-shadow: inset 0 -1px 0 rgba(11,61,36,.09);
  }
  .appbar__crumb { display: flex; align-items: center; gap: 9px; font-size: 12.5px; color: rgba(11,61,36,.5); }
  .appbar__crumb .sep { opacity: .45; }
  .appbar__crumb b { color: #0B3D24; font-weight: 600; }
  .appbar__user { display: flex; align-items: center; gap: 11px; }
  .avatar {
    width: 36px; height: 36px; border-radius: 50%; flex: none;
    background: #0C4028; color: #FAF6EC; display: flex; align-items: center; justify-content: center;
    font-size: 12.5px; font-weight: 600; box-shadow: 0 2px 6px rgba(11,61,36,.28);
  }
  .appbar__meta { display: flex; flex-direction: column; gap: 1px; line-height: 1.25; }
  .appbar__meta .nm { font-size: 12.5px; font-weight: 600; color: #0B3D24; }
  .appbar__meta .rl { font-size: 11px; color: rgba(11,61,36,.5); }
  .appbar__acts { display: flex; align-items: center; gap: 4px; margin-left: 8px; padding-left: 12px; box-shadow: inset 1px 0 0 rgba(11,61,36,.12); }
  .appbar__acts form { margin: 0; }
  .appbar__acts a, .appbar__acts button {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    height: 34px; padding: 0 10px; border-radius: 8px; border: 0; cursor: pointer;
    font: inherit; font-size: 12.5px; color: rgba(11,61,36,.66); background: transparent; text-decoration: none;
  }
  .appbar__acts a:hover, .appbar__acts button:hover { background: rgba(11,61,36,.07); color: #0B3D24; }

  .content { flex: 1; padding: 32px 40px 40px; display: flex; flex-direction: column; min-width: 0; }
  .page-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 32px; margin-bottom: 26px; }
  .page-head h1 { margin: 0; font-size: 34px; line-height: 1.05; font-weight: 700; letter-spacing: -.02em; color: #0B3D24; }
  .page-sub { margin: 9px 0 0; font-size: 15px; line-height: 1.5; color: rgba(11,61,36,.62); }
  .page-actions { display: flex; align-items: center; gap: 12px; flex: none; }

  /* botão café (gradiente verde + grão no hover) reutilizável */
  .btn-coffee {
    display: inline-flex; align-items: center; justify-content: center; gap: 9px;
    height: 44px; padding: 0 18px; border: 0; border-radius: 10px; cursor: pointer;
    font: inherit; font-weight: 600; font-size: 13.5px; color: #fff; text-decoration: none;
    background: linear-gradient(180deg, #12572F, #0A3A22);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.2), 0 8px 20px -10px rgba(10,58,34,.8);
  }
  .btn-coffee:hover { background: linear-gradient(180deg, #166936, #0C4527); }
  .btn-coffee .bean { width: 0; opacity: 0; transform: scale(.4); overflow: hidden; display: inline-flex; transition: width .22s ease, opacity .22s ease, transform .22s ease; }
  .btn-coffee:hover .bean { width: 15px; opacity: 1; transform: scale(1); animation: ut-beanroll 1.1s linear infinite; }

  /* ---------- Início (cards de atalho) ---------- */
  .home-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
  .home-card {
    display: flex; flex-direction: column; justify-content: space-between; gap: 24px;
    min-height: 208px; padding: 24px; border-radius: 16px; text-decoration: none;
    background: linear-gradient(180deg, #FFFFFF 0%, #FDFBF5 100%);
    box-shadow: 0 1px 2px rgba(11,61,36,.06), 0 10px 28px -12px rgba(11,61,36,.28), inset 0 0 0 1px rgba(11,61,36,.07);
    transition: transform .18s ease, box-shadow .18s ease;
  }
  .home-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 2px 4px rgba(11,61,36,.07), 0 22px 44px -16px rgba(11,61,36,.34), inset 0 0 0 1px rgba(11,61,36,.11);
  }
  .home-card__icon {
    width: 46px; height: 46px; border-radius: 12px; flex: none;
    background: #0C4028; color: #FAF6EC; display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 12px -3px rgba(11,61,36,.5), inset 0 1px 0 rgba(255,255,255,.22);
  }
  .home-card h2 { margin: 0 0 7px; font-size: 18px; font-weight: 700; letter-spacing: -.01em; color: #0B3D24; }
  .home-card p { margin: 0; font-size: 13px; line-height: 1.5; color: rgba(11,61,36,.55); }
  .home-foot { margin-top: auto; padding-top: 28px; display: flex; align-items: center; justify-content: space-between; font-size: 11.5px; color: rgba(11,61,36,.42); }
  .home-foot .mono { font-family: var(--font-data); letter-spacing: .05em; }

  /* ---------- Gestão de usuários ---------- */
  .notice-danger {
    display: flex; align-items: center; gap: 14px; padding: 15px 18px; border-radius: 12px; margin-bottom: 22px;
    background: rgba(232,50,44,.07); box-shadow: inset 0 0 0 1px rgba(232,50,44,.25);
  }
  .notice-danger__icon { width: 30px; height: 30px; flex: none; border-radius: 8px; background: #E8322C; display: flex; align-items: center; justify-content: center; }
  .notice-danger b { display: block; font-size: 13.5px; color: #8E1B17; }
  .notice-danger p { margin: 2px 0 0; font-size: 12.5px; color: rgba(11,61,36,.62); }

  .admin-grid { display: grid; grid-template-columns: 1fr 372px; gap: 22px; align-items: start; }
  .usercard { display: flex; flex-direction: column; border-radius: 16px; background: #fff; overflow: hidden;
    box-shadow: 0 1px 2px rgba(11,61,36,.06), 0 10px 28px -14px rgba(11,61,36,.24), inset 0 0 0 1px rgba(11,61,36,.07); }
  .utable__head, .utable__row { display: grid; grid-template-columns: 2fr 1.3fr 1fr 96px; gap: 16px; align-items: center; padding: 15px 22px; }
  .utable__head { font-family: var(--font-data); font-size: 10px; letter-spacing: .11em; text-transform: uppercase; color: rgba(11,61,36,.5); box-shadow: inset 0 -1px 0 rgba(11,61,36,.09); }
  .utable__head .r { text-align: right; }
  .utable__row { position: relative; box-shadow: inset 0 -1px 0 rgba(11,61,36,.06); }
  .utable__row:hover { background: #FBF8F1; }
  .utable__rowacts {
    position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
    display: flex; gap: 6px; opacity: 0; pointer-events: none; transition: opacity .15s ease;
    padding-left: 48px; background: linear-gradient(90deg, transparent, #FBF8F1 30%);
  }
  .utable__row:hover .utable__rowacts { opacity: 1; pointer-events: auto; }
  .utable__rowacts form { margin: 0; }
  .utable__rowacts .mini {
    display: inline-flex; align-items: center; height: 30px; padding: 0 11px; border-radius: 7px;
    font: inherit; font-size: 12.5px; cursor: pointer; text-decoration: none; border: 0;
    color: var(--primary); background: rgba(12,64,40,.08);
  }
  .utable__rowacts .mini:hover { background: rgba(12,64,40,.16); }
  .utable__user { display: flex; align-items: center; gap: 12px; min-width: 0; }
  .uavatar { width: 34px; height: 34px; flex: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11.5px; font-weight: 600; background: #0C4028; color: #FAF6EC; }
  .uavatar--brown { background: #4A2C1A; color: #F7F3EA; }
  .uavatar--muted { background: rgba(11,61,36,.14); color: rgba(11,61,36,.65); }
  .utable__name { font-size: 13.5px; font-weight: 600; color: #0B3D24; }
  .utable__email { font-size: 11.5px; color: rgba(11,61,36,.5); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .rolepill { justify-self: start; font-size: 11.5px; font-weight: 600; padding: 5px 10px; border-radius: 20px; background: rgba(12,64,40,.1); color: #0B3D24; }
  .rolepill--brown { background: rgba(74,44,26,.1); color: #4A2C1A; }
  .rolepill--muted { background: rgba(11,61,36,.08); color: rgba(11,61,36,.65); }
  .utable__last { font-size: 12.5px; color: rgba(11,61,36,.6); }
  .ustatus { justify-self: end; display: flex; align-items: center; gap: 7px; font-size: 12px; color: rgba(11,61,36,.6); white-space: nowrap; }
  .ustatus .d { width: 7px; height: 7px; border-radius: 50%; flex: none; }
  .ustatus .d--on { background: #1E9E5F; }
  .ustatus .d--off { background: #C9A227; }
  .utable__actions { display: flex; gap: 6px; padding: 14px 22px; flex-wrap: wrap; }
  .usercard__foot { margin-top: auto; padding: 14px 22px; display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: rgba(11,61,36,.5); box-shadow: inset 0 1px 0 rgba(11,61,36,.07); }
  .usercard__foot .mono { font-family: var(--font-data); letter-spacing: .06em; }

  .userform { display: flex; flex-direction: column; gap: 16px; padding: 24px; border-radius: 16px;
    background: linear-gradient(180deg, #FFFFFF 0%, #FDFBF5 100%);
    box-shadow: 0 1px 2px rgba(11,61,36,.06), 0 14px 34px -16px rgba(11,61,36,.3), inset 0 0 0 1px rgba(11,61,36,.08); }
  .userform h2 { margin: 0; font-size: 17px; font-weight: 700; letter-spacing: -.01em; color: #0B3D24; }
  .userform__lead { margin: 4px 0 0; font-size: 12.5px; line-height: 1.5; color: rgba(11,61,36,.55); }
  .userform .fields { display: flex; flex-direction: column; gap: 13px; }
  .userform label { display: flex; flex-direction: column; gap: 6px; }
  .userform .lbl { font-family: var(--font-data); font-size: 10px; letter-spacing: .1em; text-transform: uppercase; color: rgba(11,61,36,.5); }
  .userform input, .userform select { height: 42px; border-radius: 9px; font-size: 13.5px; }
  .userform .roledesc { font-size: 12.5px; line-height: 1.5; color: rgba(11,61,36,.62); padding: 12px 14px; border-radius: 9px; background: rgba(12,64,40,.05); box-shadow: inset 0 0 0 1px rgba(11,61,36,.1); }
  .userform .roledesc b { color: #0B3D24; }

  /* ---------- Cards / forms ---------- */
  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    margin-bottom: 20px;
  }
  .card__header {
    padding: 14px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
  }
  .card__header h2 { font-size: 16px; margin: 0; }
  .card__header--dark { background: var(--primary); color: #fff; border-radius: var(--radius) var(--radius) 0 0; }
  .card__header--dark h2 { color: #fff; }
  .card__body { padding: 20px; }

  .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px 20px; }
  .form-grid--3 { grid-template-columns: repeat(3, 1fr); }
  .field { display: flex; flex-direction: column; gap: 6px; }
  .field--full { grid-column: 1 / -1; }
  .field label { font-size: 13px; font-weight: 600; color: var(--ink); }
  .field .hint { font-size: 12px; color: var(--muted); }

  input[type=text], input[type=email], input[type=password], input[type=number],
  input[type=month], input[type=date], input[type=search], select, textarea {
    font: inherit; font-size: 14.5px;
    padding: 9px 11px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    background: #fff; color: var(--ink);
    width: 100%;
  }
  input:focus, select:focus, textarea:focus { border-color: var(--primary-light); }
  .field.has-error input, .field.has-error select { border-color: var(--accent); background: #FFFBFA; }
  .field-error { color: var(--danger-text); font-size: 12.5px; margin-top: 2px; }

  .btn {
    display: inline-flex; align-items: center; gap: 8px;
    font: inherit; font-size: 14px; font-weight: 600;
    padding: 10px 18px; border-radius: var(--radius-sm);
    border: 1px solid transparent; cursor: pointer; text-decoration: none;
  }
  .btn-primary { background: var(--primary); color: #fff; }
  .btn-primary:hover { background: var(--primary-light); }
  .btn-accent { background: var(--accent); color: #fff; }
  .btn-accent:hover { background: var(--accent-dark); }
  .btn-ghost { background: transparent; color: var(--primary); border-color: var(--border); }
  .btn-ghost:hover { background: var(--primary-soft); }
  .btn[disabled] { opacity: .55; cursor: not-allowed; }

  /* ---------- Tables ---------- */
  .table-wrap { overflow-x: auto; border-radius: var(--radius); border: 1px solid var(--border); }
  table.data { width: 100%; border-collapse: collapse; background: #fff; font-size: 14px; }
  table.data thead th {
    background: var(--primary-dark); color: #fff;
    text-align: left; padding: 12px 14px; font-size: 12.5px;
    text-transform: uppercase; letter-spacing: .05em; white-space: nowrap;
  }
  table.data tbody td { padding: 11px 14px; border-bottom: 1px solid var(--border); }
  table.data tbody tr:nth-child(even) { background: #FAF8F2; }
  table.data tbody tr:hover { background: var(--primary-soft); }
  table.data .num { font-family: var(--font-data); font-variant-numeric: tabular-nums; text-align: right; }
  table.data tfoot td { font-weight: 700; padding: 12px 14px; border-top: 2px solid var(--primary); }

  /* ---------- Badges / alerts ---------- */
  .badge { display: inline-block; padding: 3px 10px; border-radius: 99px; font-size: 12px; font-weight: 600; }
  .badge--green { background: var(--primary-soft); color: var(--primary); }
  .badge--red { background: var(--danger-bg); color: var(--danger-text); }
  .badge--muted { background: #EEEBE1; color: var(--muted); }

  .alert { padding: 12px 16px; border-radius: var(--radius-sm); margin-bottom: 18px; font-size: 14px; }
  .alert-success { background: var(--success-bg); color: var(--success-text); border: 1px solid #BFE0C9; }
  .alert-error { background: var(--danger-bg); color: var(--danger-text); border: 1px solid #F0C4BE; }
  .alert code { background: rgba(0,0,0,.06); padding: 1px 6px; border-radius: 4px; }

  .filter-bar { display: flex; gap: 12px; align-items: end; margin-bottom: 18px; flex-wrap: wrap; }
  .filter-bar .field { min-width: 180px; }

  .pagination { margin-top: 16px; }

  /* ---------- Tabela em cards (telas estreitas) ----------
     Em vez de rolar uma tabela larga na horizontal, cada linha vira um
     card com "rótulo: valor". Só as células com data-label aparecem;
     use .cell-hide-mobile para omitir uma célula redundante no card. */
  @media (max-width: 720px) {
    table.data--cards, table.data--cards tbody, table.data--cards tr, table.data--cards td {
      display: block; width: 100%;
    }
    table.data--cards thead { display: none; }
    table.data--cards { font-size: 14px; }
    table.data--cards tbody tr {
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      background: #fff;
      margin: 0 0 12px;
      padding: 6px 14px;
      box-shadow: var(--shadow);
    }
    table.data--cards tbody tr:nth-child(even) { background: #fff; }
    table.data--cards tbody tr:hover { background: #fff; }
    table.data--cards tbody td {
      display: flex; align-items: baseline; justify-content: space-between; gap: 14px;
      padding: 7px 0;
      border-bottom: 1px solid var(--border);
      text-align: right;
    }
    table.data--cards tbody tr td:last-child { border-bottom: none; }
    table.data--cards tbody td::before {
      content: attr(data-label);
      font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
      color: var(--muted); text-align: left; white-space: nowrap;
    }
    table.data--cards td.num { text-align: right; }
    table.data--cards td.cell-hide-mobile { display: none; }
    /* Célula de ação (botão Abrir) ocupa a largura toda */
    table.data--cards td.cell-action { justify-content: stretch; padding-top: 10px; }
    table.data--cards td.cell-action::before { display: none; }
    table.data--cards td.cell-action .btn { width: 100%; justify-content: center; }
    /* Linha "nenhum resultado" volta a ser uma célula simples */
    table.data--cards td.cell-empty { display: block; text-align: center; }
    table.data--cards td.cell-empty::before { display: none; }
  }

  /* ---------- Guest (login/senha) layout ---------- */
  .guest-shell {
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(160deg, var(--primary-dark), var(--primary) 55%, var(--primary-light));
    padding: 24px;
  }
  .guest-card {
    width: 100%; max-width: 400px; background: var(--surface);
    border-radius: var(--radius); box-shadow: 0 20px 50px rgba(11,42,28,.35);
    padding: 32px 30px;
  }
  .guest-card__brand { text-align: center; margin-bottom: 22px; }
  .guest-card__brand .brand-u { font-family: var(--font-display); font-size: 30px; color: var(--primary); letter-spacing: .06em; }
  .guest-card__brand .brand-sub { font-size: 11px; letter-spacing: .18em; text-transform: uppercase; color: var(--muted); }
  .guest-card h1 { font-size: 18px; text-align: center; margin-bottom: 18px; }
  .guest-links { text-align: center; margin-top: 16px; font-size: 13.5px; }
  .guest-links a { color: var(--primary); text-decoration: underline; }

  /* ============================================================
     Tela de LOGIN — cena "café" (fundo verde→marrom, grãos caindo,
     card de vidro fosco). Reproduz o design compartilhado sem fontes
     externas nem imagens, com fallback e prefers-reduced-motion para
     rodar leve em qualquer dispositivo.
     ============================================================ */
  .login-page {
    min-height: 100vh; min-height: 100dvh;
    display: flex; align-items: center; justify-content: center;
    padding: 24px; position: relative; overflow: hidden;
    font-family: var(--font-body);
    background: linear-gradient(150deg, #0A3521 0%, #0C4028 38%, #3B2415 78%, #20150C 100%);
  }
  /* brilhos radiais (verde no topo-esq., âmbar embaixo-dir.) */
  .login-page::before {
    content: ""; position: absolute; inset: 0; pointer-events: none;
    background:
      radial-gradient(60% 50% at 22% 18%, rgba(122,214,163,.16) 0%, rgba(122,214,163,0) 70%),
      radial-gradient(50% 45% at 82% 76%, rgba(196,138,84,.20) 0%, rgba(196,138,84,0) 72%);
  }

  /* camadas de fundo animadas */
  .login-fx { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
  .login-steam { position: absolute; inset: 0; overflow: hidden; filter: blur(24px); opacity: .7; }
  .login-steam i { position: absolute; border-radius: 50%; display: block; }
  .login-bean { position: absolute; top: 0; will-change: transform; }
  .login-bean svg { display: block; }

  /* card de vidro fosco */
  .login-card {
    position: relative; z-index: 2;
    width: min(452px, 100%);
    padding: 38px 40px 30px; border-radius: 22px;
    color: #FBF8F1;
    background: rgba(247,243,236,.13);
    -webkit-backdrop-filter: blur(26px) saturate(150%);
    backdrop-filter: blur(26px) saturate(150%);
    box-shadow:
      inset 0 1px 0 rgba(255,255,255,.35),
      inset 0 0 0 1px rgba(255,255,255,.16),
      0 32px 80px -24px rgba(0,0,0,.6);
    display: flex; flex-direction: column; gap: 22px;
  }
  /* fallback: navegadores sem backdrop-filter recebem fundo sólido escuro */
  @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
    .login-card { background: rgba(16,44,29,.9); }
  }

  .login-head { display: flex; flex-direction: column; align-items: center; gap: 16px; }
  .login-brandtile {
    border-radius: 16px; background: #FAF6EC; padding: 16px 22px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 8px 24px -10px rgba(0,0,0,.5);
  }
  .logo-union { display: block; height: 138px; width: auto; }
  .login-title { margin: 0; font-family: var(--font-display); font-size: 24px; font-weight: 700; letter-spacing: -.01em; color: #FBF8F1; text-align: center; }
  .login-sub { margin: 4px 0 0; font-size: 14px; color: rgba(251,248,241,.68); text-align: center; }

  .login-fields { display: flex; flex-direction: column; gap: 14px; }
  .login-field { display: flex; flex-direction: column; gap: 7px; }
  .login-field .lbl { font-family: var(--font-data); font-size: 11.5px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: rgba(251,248,241,.62); }
  .login-inputwrap {
    display: flex; align-items: center; gap: 11px; height: 48px; padding: 0 15px;
    border-radius: 11px; background: rgba(255,255,255,.11);
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.20);
  }
  .login-inputwrap:focus-within { background: rgba(255,255,255,.16); box-shadow: inset 0 0 0 1px rgba(255,255,255,.55); }
  .login-inputwrap.has-error { box-shadow: inset 0 0 0 1px rgba(232,140,130,.85); }
  .login-inputwrap svg { flex: none; }
  /* input transparente sobrepondo o estilo global de inputs */
  .login-inputwrap input {
    flex: 1; min-width: 0; width: 100%;
    border: 0; background: transparent; box-shadow: none;
    color: #FBF8F1; font-size: 14.5px; padding: 0;
  }
  .login-inputwrap input:focus, .login-inputwrap input:focus-visible { outline: none; border: 0; }
  .login-inputwrap input::placeholder { color: rgba(251,248,241,.5); }
  .login-inputwrap input:-webkit-autofill,
  .login-inputwrap input:-webkit-autofill:focus {
    -webkit-text-fill-color: #FBF8F1;
    -webkit-box-shadow: 0 0 0 60px rgba(20,52,34,.001) inset;
    transition: background-color 9999s ease-in-out 0s;
  }

  .login-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; font-size: 13px; }
  .login-check { display: flex; align-items: center; gap: 10px; color: rgba(251,248,241,.82); cursor: pointer; user-select: none; }
  .login-check input { position: absolute; opacity: 0; width: 1px; height: 1px; }
  .login-check .box {
    width: 18px; height: 18px; border-radius: 5px; flex: none;
    background: rgba(255,255,255,.10); box-shadow: inset 0 0 0 1px rgba(255,255,255,.35);
    display: flex; align-items: center; justify-content: center;
  }
  .login-check .box svg { opacity: 0; }
  .login-check input:checked + .box { background: #0C4028; }
  .login-check input:checked + .box svg { opacity: 1; }
  .login-check input:focus-visible + .box { outline: 3px solid var(--accent); outline-offset: 2px; }
  .login-forgot { color: rgba(251,248,241,.72); text-decoration: none; box-shadow: inset 0 -1px 0 rgba(251,248,241,.35); white-space: nowrap; }
  .login-forgot:hover { color: #fff; }

  .login-btn {
    height: 52px; width: 100%; border: 0; border-radius: 12px; cursor: pointer;
    font-family: var(--font-body); font-size: 15px; font-weight: 600; letter-spacing: .02em; color: #fff;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    background: linear-gradient(180deg, #12572F 0%, #0A3A22 100%);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.22), 0 12px 28px -12px rgba(10,58,34,.9);
  }
  .login-btn:hover { background: linear-gradient(180deg, #166936 0%, #0C4527 100%); }
  .login-btn .bean { width: 0; opacity: 0; transform: scale(.4); overflow: hidden; display: inline-flex; transition: width .22s ease, opacity .22s ease, transform .22s ease; }
  .login-btn:hover .bean { width: 16px; opacity: 1; transform: scale(1); animation: ut-beanroll 1.1s linear infinite; }

  .login-alert { border-radius: 11px; padding: 11px 14px; font-size: 13px; display: flex; flex-direction: column; gap: 3px;
    background: rgba(176,58,46,.24); color: #FCECE9; box-shadow: inset 0 0 0 1px rgba(224,122,110,.5); }
  .login-alert--ok { background: rgba(28,92,62,.30); color: #EAF6EE; box-shadow: inset 0 0 0 1px rgba(122,214,163,.45); }

  .login-foot { margin: 8px 0 0; text-align: center; font-family: var(--font-data);
    font-size: 10px; letter-spacing: .1em; text-transform: uppercase; color: rgba(251,248,241,.4); }

  @keyframes ut-fall {
    0% { transform: translate3d(0,-140px,0) rotate(0deg); opacity: 0; }
    8% { opacity: .85; } 92% { opacity: .85; }
    100% { transform: translate3d(40px,105vh,0) rotate(320deg); opacity: 0; }
  }
  @keyframes ut-steam {
    0% { transform: translate3d(0,60px,0) scale(.7); opacity: 0; }
    25% { opacity: .5; }
    100% { transform: translate3d(30px,-320px,0) scale(1.9); opacity: 0; }
  }
  @keyframes ut-drift {
    0% { transform: translate3d(0,0,0) scale(1); }
    50% { transform: translate3d(-24px,-18px,0) scale(1.06); }
    100% { transform: translate3d(0,0,0) scale(1); }
  }
  @keyframes ut-beanroll {
    0% { transform: translateX(-6px) rotate(-30deg); }
    100% { transform: translateX(4px) rotate(310deg); }
  }
  /* Nota: por escolha do projeto, os grãos e o vapor caem SEMPRE, mesmo com
     "reduzir movimento" ligado no sistema (a animação é leve e é a identidade
     da tela). Se um dia quiser respeitar a preferência de acessibilidade,
     basta reintroduzir um bloco @media (prefers-reduced-motion: reduce) com
     `.login-bean, .login-steam i { animation-play-state: paused !important; }`. */

  @media (max-width: 520px) {
    .login-card { padding: 30px 22px 24px; border-radius: 18px; }
    .login-title { font-size: 21px; }
  }

  /* cards do Início e grid da Gestão em telas médias */
  @media (max-width: 1180px) {
    .home-cards { grid-template-columns: repeat(2, 1fr); }
    .admin-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 860px) {
    .form-grid, .form-grid--3 { grid-template-columns: 1fr; }
    .home-cards { grid-template-columns: 1fr; }
  }
  @media (max-width: 780px) {
    .app-shell { flex-direction: column; }
    .sidebar { width: 100%; }
    .sidebar__logo { height: 96px; }
    .sidebar__logo img { height: 68px; }
    .sidebar__nav { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 12px; padding: 12px; }
    .sb-group { gap: 2px; }
    .content { padding: 22px 18px 32px; }
    .appbar { padding: 0 18px; height: 64px; }
    .page-head { flex-direction: column; align-items: flex-start; gap: 14px; }
    .page-head h1 { font-size: 27px; }
    .utable__head { display: none; }
    .utable__row { grid-template-columns: 1fr auto; grid-auto-rows: min-content; gap: 8px 12px; padding-bottom: 52px; }
    .utable__rowacts { position: absolute; left: 22px; right: auto; bottom: 12px; top: auto; transform: none;
      opacity: 1; pointer-events: auto; padding-left: 0; background: none; }
  }
</style>
