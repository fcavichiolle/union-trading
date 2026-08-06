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

  /* ---------- App shell ---------- */
  .app-shell { display: flex; min-height: 100vh; }

  .sidebar {
    width: 250px;
    flex-shrink: 0;
    background: var(--primary-dark);
    color: #EFEDE3;
    display: flex;
    flex-direction: column;
  }
  .sidebar__brand {
    padding: 22px 20px;
    background: var(--primary);
  }
  .sidebar__brand .brand-u { font-family: var(--font-display); font-size: 26px; font-weight: 700; color: #fff; letter-spacing: .06em; }
  .sidebar__brand .brand-sub { font-size: 11px; letter-spacing: .18em; text-transform: uppercase; color: #B7CFC1; margin-top: 2px; }

  .sidebar__nav { padding: 14px 10px; flex: 1; overflow-y: auto; }
  .sidebar__group-label { font-size: 11px; text-transform: uppercase; letter-spacing: .12em; color: #7FA391; padding: 14px 12px 6px; }
  .sidebar__link {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; margin: 1px 0;
    border-radius: var(--radius-sm);
    color: #DCE6DF; text-decoration: none; font-size: 14.5px;
  }
  .sidebar__link:hover { background: rgba(255,255,255,.08); color: #fff; }
  .sidebar__link.is-active { background: var(--primary-light); color: #fff; font-weight: 600; }

  .sidebar__footer { padding: 14px 16px; border-top: 1px solid rgba(255,255,255,.1); font-size: 13px; }
  .sidebar__user { color: #EFEDE3; font-weight: 600; }
  .sidebar__role { color: #9BB8A9; font-size: 12px; }
  .sidebar__logout {
    display: inline-block; margin-top: 8px; font-size: 13px; color: #E8B4AC;
    background: none; border: 0; padding: 0; cursor: pointer; text-decoration: underline;
  }

  .main { flex: 1; min-width: 0; }
  .topbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 28px; background: var(--surface); border-bottom: 1px solid var(--border);
  }
  .topbar h1 { font-size: 21px; margin: 0; }
  .content { padding: 24px 28px 60px; }

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

  @media (max-width: 860px) {
    .form-grid, .form-grid--3 { grid-template-columns: 1fr; }
  }
  @media (max-width: 780px) {
    .app-shell { flex-direction: column; }
    .sidebar { width: 100%; flex-direction: row; align-items: center; flex-wrap: wrap; }
    .sidebar__nav { display: flex; flex-wrap: wrap; padding: 8px; }
    .sidebar__group-label { display: none; }
    .sidebar__footer { margin-left: auto; border-top: none; }
    .content { padding: 18px; }
    .topbar { padding: 14px 18px; }
  }
</style>
