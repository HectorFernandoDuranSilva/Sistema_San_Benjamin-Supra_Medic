<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Sistema de Inventarios' }}</title>
    <script>
        if (localStorage.getItem('theme-mode') === 'dark') {
            document.documentElement.classList.add('theme-dark');
        }
    </script>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family:Arial, Helvetica, sans-serif; color:#172033; background:#f6f8fc; letter-spacing:0; }
        a { color:inherit; text-decoration:none; }
        input, button, select, textarea { font:inherit; }
        .login-shell { min-height:100vh; display:grid; place-items:center; padding:38px; position:relative; overflow:hidden; }
        .field { margin-bottom:13px; }
        label { display:block; font-size:12px; font-weight:700; margin-bottom:6px; }
        input, select, textarea { width:100%; border:1px solid rgba(103,190,217,.22); border-radius:6px; padding:11px 12px; background:rgba(255,255,255,.88); color:#172033; box-shadow:inset 0 1px 0 rgba(255,255,255,.95), 0 6px 14px rgba(73,102,140,.07); }
        input:focus, select:focus, textarea:focus { outline:none; border-color:rgba(103,190,217,.7); box-shadow:inset 0 1px 0 rgba(255,255,255,.95), 0 0 0 3px rgba(103,190,217,.18), 0 8px 18px rgba(73,102,140,.10); }
        input[readonly] { background:rgba(255,255,255,.68); border-color:rgba(103,190,217,.16); color:#334155; }
        button { width:100%; border:0; border-radius:6px; padding:12px; background:var(--primary); color:#fff; font-weight:700; cursor:pointer; }
        .brand-mark { width:46px; height:46px; display:grid; place-items:center; border-radius:7px; color:#fff; background:var(--primary); font-weight:800; margin-bottom:18px; }
        .dash-shell { min-height:100vh; display:grid; grid-template-columns:248px 1fr; background:var(--dash-bg); }
        .side { padding:24px 16px; background:var(--side); color:var(--side-text); }
        .side .brand { font-size:19px; font-weight:800; margin-bottom:22px; }
        .side a { display:block; padding:10px 12px; border-radius:6px; color:inherit; opacity:.86; border:1px solid rgba(255,255,255,.16); box-shadow:inset 0 1px 0 rgba(255,255,255,.18), 0 6px 14px rgba(15,40,70,.08); }
        .side a.active, .side a:hover { background:var(--side-active); opacity:1; border-color:rgba(255,255,255,.34); }
        .content { padding:28px; }
        .dash-top { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:20px; }
        .dash-top h1 { margin:0; font-size:26px; }
        .dash-actions { display:flex; gap:10px; }
        .dash-actions a { padding:10px 12px; border-radius:6px; background:var(--primary); color:#fff; font-size:13px; border:1px solid rgba(255,255,255,.42); box-shadow:inset 0 1px 0 rgba(255,255,255,.28), 0 10px 22px rgba(43,145,201,.18); }
        .dash-actions a.secondary { background:#fff; color:var(--primary); border:1px solid rgba(103,190,217,.38); box-shadow:inset 0 1px 0 rgba(255,255,255,.9), 0 8px 18px rgba(73,102,140,.12); }
        .commercial-select { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:8px; background:var(--panel); border:1px solid var(--line); box-shadow:var(--shadow); }
        .commercial-select span { font-size:12px; color:var(--muted); font-weight:700; }
        .commercial-select select { width:auto; min-width:150px; padding:7px 9px; }
        .commercial-select img { width:72px; height:36px; object-fit:contain; padding:4px; border-radius:6px; background:#fff; border:1px solid rgba(90,120,160,.14); }
        .cards { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:14px; margin-bottom:16px; }
        .metric, .panel { background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:16px; box-shadow:var(--shadow); }
        .metric span { color:var(--muted); font-size:12px; }
        .metric strong { display:block; margin-top:8px; font-size:25px; }
        .dashboard-grid { display:grid; grid-template-columns:1.15fr .85fr; gap:16px; }
        table { width:100%; border-collapse:separate; border-spacing:0; }
        th, td { padding:10px 6px; border-bottom:1px solid rgba(103,190,217,.16); text-align:left; font-size:13px; }
        th { color:var(--muted); font-size:11px; text-transform:uppercase; }
        .badge { display:inline-block; padding:5px 8px; border-radius:6px; background:var(--soft); color:var(--primary); font-size:12px; font-weight:700; }
        .monthly-summary { display:grid; gap:18px; }
        .monthly-summary-head { display:flex; justify-content:space-between; gap:18px; align-items:flex-start; }
        .monthly-summary-head h2 { margin:5px 0 4px; }
        .monthly-summary-head p { margin:0; font-size:13px; }
        .eyebrow { color:var(--primary); font-size:11px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
        .monthly-status { display:grid; gap:3px; min-width:220px; padding:11px 13px; border-radius:8px; border:1px solid var(--line); }
        .monthly-status strong { font-size:16px; }
        .monthly-status small { color:var(--muted); }
        .monthly-status.is-positive { border-color:rgba(22,163,74,.35); background:rgba(220,252,231,.75); color:#166534; }
        .monthly-status.is-negative { border-color:rgba(220,38,38,.3); background:rgba(254,226,226,.8); color:#991b1b; }
        .monthly-kpis { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
        .monthly-kpi { min-width:0; padding:15px; border:1px solid var(--line); border-radius:8px; background:rgba(255,255,255,.58); }
        .monthly-kpi span { display:block; color:var(--muted); font-size:12px; font-weight:700; }
        .monthly-kpi strong { display:block; margin-top:7px; font-size:21px; }
        .monthly-kpi small { display:block; margin-top:6px; color:var(--muted); font-size:12px; }
        .monthly-kpi.sales { border-top:3px solid #2563eb; }
        .monthly-kpi.purchases { border-top:3px solid #d97706; }
        .monthly-kpi.result.is-positive { border-top:3px solid #16a34a; }
        .monthly-kpi.result.is-negative { border-top:3px solid #dc2626; }
        .monthly-kpi.result.is-positive strong { color:#15803d; }
        .monthly-kpi.result.is-negative strong { color:#b91c1c; }
        .monthly-comparison { display:grid; gap:8px; }
        .monthly-comparison-labels { display:flex; justify-content:space-between; color:var(--muted); font-size:12px; font-weight:700; }
        .monthly-bar { display:flex; height:12px; overflow:hidden; border-radius:999px; background:var(--soft); }
        .monthly-bar-sales { background:#2563eb; }
        .monthly-bar-purchases { background:#d97706; }
        .monthly-explanation { margin:0; color:var(--muted); font-size:13px; }
        .monthly-explanation strong { color:#172033; }
        .timeline { display:grid; gap:12px; }
        .timeline-row { display:grid; grid-template-columns:76px 1fr auto; gap:12px; align-items:center; padding-bottom:12px; border-bottom:1px solid var(--line); }
        .dashboard-bottom { display:grid; grid-template-columns:1fr 1.2fr 1fr; gap:16px; margin-top:16px; align-items:start; }
        .compact-list { display:grid; gap:10px; }
        .compact-item { display:grid; grid-template-columns:1fr auto; gap:12px; align-items:center; padding:10px 0; border-bottom:1px solid var(--line); }
        .compact-item:last-child { border-bottom:0; }
        .compact-item b { display:block; font-size:13px; }
        .compact-item small { display:block; margin-top:4px; color:var(--muted); }
        .alert-item { padding:12px; border:1px solid rgba(103,190,217,.18); border-radius:8px; background:rgba(255,255,255,.38); box-shadow:inset 0 1px 0 rgba(255,255,255,.58), 0 8px 18px rgba(73,102,140,.08); }
        .alert-item strong { display:flex; justify-content:space-between; gap:10px; font-size:13px; }
        .pill { display:inline-block; padding:5px 8px; border-radius:999px; background:var(--soft); color:var(--primary); font-size:12px; font-weight:700; }
        .muted { color:var(--muted); }
        .notice { padding:10px 12px; border-radius:6px; margin-bottom:12px; background:#e6fffb; border:1px solid #99f6e4; }
        .errors { padding:10px 12px; border-radius:6px; margin-bottom:12px; background:#fff1f2; border:1px solid #fecdd3; color:#991b1b; }
        .actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .btn { display:inline-block; border:1px solid rgba(29,120,172,.36); background:var(--primary); color:#fff; border-radius:6px; padding:8px 11px; cursor:pointer; font:inherit; box-shadow:inset 0 1px 0 rgba(255,255,255,.32), 0 8px 18px rgba(43,145,201,.16); }
        .btn.secondary { background:#fff; color:var(--primary); border-color:rgba(103,190,217,.42); box-shadow:inset 0 1px 0 rgba(255,255,255,.92), 0 8px 18px rgba(73,102,140,.12); }
        .btn.danger { background:#b91c1c; border-color:#b91c1c; }
        .btn.force-danger { background:#7f1d1d; border-color:#7f1d1d; }
        .force-delete { margin-top:8px; padding:8px; border:1px solid rgba(185,28,28,.22); border-radius:8px; background:rgba(255,241,242,.58); }
        .force-delete summary { cursor:pointer; color:#991b1b; font-size:12px; font-weight:800; }
        .force-delete input { margin:8px 0; min-width:210px; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:18px; }
        .top h1 { margin:0; font-size:26px; }
        .card { background:var(--panel); border:1px solid rgba(255,255,255,.82); border-radius:8px; padding:16px; box-shadow:inset 0 1px 0 rgba(255,255,255,.74), var(--shadow); backdrop-filter:blur(16px); overflow:auto; }
        .card .field { padding:8px; border:1px solid rgba(103,190,217,.10); border-radius:8px; background:rgba(255,255,255,.22); box-shadow:inset 0 1px 0 rgba(255,255,255,.58); }
        .card .card[style*="padding:0"] { border-color:rgba(103,190,217,.20); box-shadow:inset 0 1px 0 rgba(255,255,255,.74), 0 10px 24px rgba(73,102,140,.10); }
        #items thead th { background:rgba(255,255,255,.55); border-top:1px solid rgba(103,190,217,.14); }
        #items tbody td { background:rgba(255,255,255,.50); }
        #items tbody tr:hover td { background:rgba(234,247,251,.72); }
        #items tfoot th { background:rgba(255,255,255,.66); font-weight:800; }
        .row { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; }
        .stat { font-size:26px; font-weight:700; margin-top:6px; }
        .grid { display:grid; gap:16px; }
        .grid.cols-4 { grid-template-columns:repeat(4,minmax(0,1fr)); }
        .grid.cols-2 { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .pagination, nav[role="navigation"] { margin-top:14px; font-size:13px; color:#1e3a5f; }
        nav[role="navigation"] svg { width:18px; height:18px; display:inline-block; vertical-align:middle; }
        nav[role="navigation"] > div { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
        nav[role="navigation"] a, nav[role="navigation"] span[aria-current] span, nav[role="navigation"] span[aria-disabled="true"] span { display:inline-flex; align-items:center; justify-content:center; min-width:34px; min-height:34px; padding:7px 10px; border:1px solid rgba(103,190,217,.26); border-radius:8px; background:rgba(255,255,255,.62); box-shadow:inset 0 1px 0 rgba(255,255,255,.75), 0 6px 14px rgba(73,102,140,.07); }
        nav[role="navigation"] span[aria-current] span { background:var(--primary); color:#fff; border-color:rgba(29,120,172,.36); }
        nav[role="navigation"] span[aria-disabled="true"] span { opacity:.45; }
        .glass-login { width:min(980px,100%); min-height:600px; display:grid; place-items:center; border-radius:18px; background:linear-gradient(135deg,#e6f3ff,#f8fbff 45%,#eef2ff); position:relative; overflow:hidden; }
        .glass-login:before { content:""; position:absolute; width:420px; height:420px; border-radius:50%; background:#a7d8ff; left:-90px; top:-100px; opacity:.55; }
        .glass-login:after { content:""; position:absolute; width:360px; height:360px; border-radius:50%; background:#c7d2fe; right:-80px; bottom:-90px; opacity:.55; }
        .glass-form { width:min(430px,90%); position:relative; z-index:1; padding:42px; border-radius:14px; background:rgba(255,255,255,.72); border:1px solid rgba(255,255,255,.88); box-shadow:0 24px 70px rgba(73,102,140,.18); backdrop-filter:blur(18px); }
        .glass-dock { position:sticky; top:12px; z-index:12; margin:16px auto 0; width:max-content; max-width:calc(100% - 32px); display:flex; align-items:center; gap:8px; padding:8px; border-radius:14px; background:rgba(255,255,255,.64); border:1px solid rgba(255,255,255,.9); box-shadow:0 18px 46px rgba(73,102,140,.16); backdrop-filter:blur(16px); }
        .glass-dock a, .glass-dock button { width:auto; padding:10px 12px; border-radius:10px; color:#1e3a5f; font-weight:700; background:rgba(255,255,255,.38); border:1px solid rgba(103,190,217,.18); box-shadow:inset 0 1px 0 rgba(255,255,255,.72), 0 7px 16px rgba(73,102,140,.08); }
        .glass-dock a.active, .glass-dock a:hover, .glass-dock button:hover { background:#fff; border-color:rgba(103,190,217,.42); box-shadow:inset 0 1px 0 rgba(255,255,255,.95), 0 10px 22px rgba(73,102,140,.14); }
        .glass-dock .dock-group { position:relative; }
        .glass-dock .dock-trigger.active { background:#fff; border-color:rgba(103,190,217,.42); box-shadow:inset 0 1px 0 rgba(255,255,255,.95), 0 10px 22px rgba(73,102,140,.14); }
        .glass-dock .dock-menu { position:absolute; top:calc(100% + 8px); left:0; min-width:190px; display:none; flex-direction:column; gap:6px; padding:8px; border-radius:12px; background:rgba(255,255,255,.86); border:1px solid rgba(255,255,255,.92); box-shadow:0 18px 42px rgba(73,102,140,.18); backdrop-filter:blur(18px); }
        /* Keep the hover path connected across the gap above the submenu. */
        .glass-dock .dock-menu::before { content:""; position:absolute; left:0; right:0; top:-9px; height:9px; }
        .glass-dock .dock-group:hover .dock-menu, .glass-dock .dock-group:focus-within .dock-menu { display:flex; }
        .glass-dock .dock-menu a { display:flex; width:100%; white-space:nowrap; justify-content:flex-start; }
        .login-logo { position:absolute; top:24px; width:220px; max-width:38vw; height:94px; object-fit:contain; padding:12px; border-radius:14px; background:rgba(255,255,255,.72); border:1px solid rgba(255,255,255,.88); box-shadow:0 24px 70px rgba(73,102,140,.14); backdrop-filter:blur(18px); z-index:2; }
        .login-logo.left { left:24px; }
        .login-logo.right { right:24px; }
        .glass-login .login-logo.left { top:8px; left:8px; }
        .glass-login .login-logo.right { top:auto; right:8px; bottom:8px; width:220px; height:94px; }
        .exchange-box { display:flex; justify-content:space-between; gap:10px; padding:10px 12px; border-radius:8px; background:rgba(255,255,255,.62); border:1px solid rgba(103,190,217,.35); margin:16px 0; color:#174b5f; }
        .login-note { font-size:12px; color:#4f6b78; margin-top:10px; text-align:center; }
        .theme-switch-row { display:flex; align-items:center; justify-content:space-between; gap:12px; margin:0 0 16px; padding:10px 12px; border-radius:10px; background:rgba(255,255,255,.54); border:1px solid rgba(103,190,217,.24); box-shadow:inset 0 1px 0 rgba(255,255,255,.78), 0 8px 18px rgba(73,102,140,.08); }
        .theme-switch-row span { font-size:13px; font-weight:800; color:#174b5f; }
        .theme-toggle { position:relative; display:inline-flex; align-items:center; width:72px; height:36px; cursor:pointer; }
        .theme-toggle input { position:absolute; opacity:0; pointer-events:none; }
        .theme-toggle-track { position:absolute; inset:0; border-radius:999px; background:linear-gradient(135deg,#dff6ff,#ffffff); border:1px solid rgba(103,190,217,.42); box-shadow:inset 0 1px 3px rgba(73,102,140,.12), 0 8px 18px rgba(73,102,140,.10); transition:background .28s ease, border-color .28s ease; }
        .theme-toggle-knob { position:absolute; top:4px; left:4px; width:28px; height:28px; border-radius:50%; background:#fff; box-shadow:0 7px 16px rgba(73,102,140,.18); transition:transform .28s cubic-bezier(.2,.8,.2,1), background .28s ease; }
        .theme-toggle input:checked + .theme-toggle-track { background:linear-gradient(135deg,#18263a,#0e1726); border-color:rgba(103,190,217,.34); }
        .theme-toggle input:checked + .theme-toggle-track .theme-toggle-knob { transform:translateX(36px); background:#67bed9; }
        .selection-options { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin:18px 0; }
        .selection-option { position:relative; display:block; padding:14px; border:1px solid rgba(103,190,217,.28); border-radius:10px; background:rgba(255,255,255,.62); cursor:pointer; box-shadow:inset 0 1px 0 rgba(255,255,255,.88), 0 10px 24px rgba(73,102,140,.10); }
        .selection-option input { position:absolute; opacity:0; pointer-events:none; }
        .selection-option img { width:100%; height:72px; object-fit:contain; padding:8px; border-radius:8px; background:#fff; margin-bottom:8px; }
        .selection-option strong { display:block; color:#174b5f; }
        .selection-option span { display:block; color:var(--muted); font-size:12px; margin-top:4px; }
        .selection-option:has(input:checked) { border-color:var(--secondary); box-shadow:0 14px 30px rgba(103,190,217,.18); background:rgba(255,255,255,.86); }
        .glass-modal-backdrop { position:fixed; inset:0; z-index:60; display:none; align-items:center; justify-content:center; padding:22px; background:rgba(20,45,70,.22); backdrop-filter:blur(12px); }
        .glass-modal-backdrop.is-open { display:flex; }
        .glass-modal { width:min(430px,100%); padding:26px; border-radius:16px; background:rgba(255,255,255,.78); border:1px solid rgba(255,255,255,.88); box-shadow:0 28px 80px rgba(31,64,94,.24); color:#172033; }
        .glass-modal.is-error { border-color:rgba(244,63,94,.34); }
        .glass-modal.is-success { border-color:rgba(103,190,217,.46); }
        .glass-modal h2 { margin:0 0 10px; font-size:24px; }
        .glass-modal p { margin:0; color:#334155; line-height:1.35; }
        .glass-modal-list { margin:0; padding-left:18px; color:#334155; line-height:1.45; }
        .glass-modal-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:22px; }
        .glass-modal-actions button { width:auto; min-width:108px; }
        .theme-glass { --primary:#67bed9; --secondary:#2f8bd8; --soft:#eaf7fb; --muted:#55708c; --line:rgba(255,255,255,.76); --panel:rgba(255,255,255,.72); --dash-bg:linear-gradient(135deg,#e8f4ff,#f8fbff 42%,#eef2ff); --side:#fff; --side-text:#1e3a5f; --side-active:#fff; --shadow:0 18px 46px rgba(73,102,140,.14); }
        .theme-glass .login-shell { background:#67bed9; }
        .theme-glass .dash-shell { display:block; }
        .theme-glass .side { display:none; }
        .theme-glass .panel, .theme-glass .metric, .theme-glass .commercial-select { backdrop-filter:blur(16px); }
        html.theme-dark body { color:#dbeafe; background:#0b1220; }
        html.theme-dark .theme-glass { --primary:#67bed9; --secondary:#2f8bd8; --soft:rgba(103,190,217,.16); --muted:#9eb7c8; --line:rgba(148,197,218,.22); --panel:rgba(13,25,42,.72); --dash-bg:linear-gradient(135deg,#07111f,#101b2d 48%,#172236); --side:#0b1220; --side-text:#dbeafe; --side-active:rgba(103,190,217,.18); --shadow:0 18px 46px rgba(0,0,0,.32); }
        html.theme-dark .theme-glass .login-shell { background:#102a3a; }
        html.theme-dark .glass-login { background:linear-gradient(135deg,#102a3a,#0c1728 48%,#18233b); box-shadow:0 28px 90px rgba(0,0,0,.22); }
        html.theme-dark .glass-login:before { background:#1f5d78; opacity:.52; }
        html.theme-dark .glass-login:after { background:#1d2f63; opacity:.58; }
        html.theme-dark .glass-form, html.theme-dark .glass-modal { background:rgba(13,25,42,.78); border-color:rgba(148,197,218,.22); color:#e6f2ff; box-shadow:0 28px 80px rgba(0,0,0,.34); }
        html.theme-dark .glass-form p, html.theme-dark .glass-modal p, html.theme-dark .glass-modal-list { color:#b8cad8; }
        html.theme-dark .login-logo, html.theme-dark .selection-option img, html.theme-dark .commercial-select img { background:rgba(255,255,255,.9); }
        html.theme-dark input, html.theme-dark select, html.theme-dark textarea { background:rgba(7,17,31,.78); color:#e6f2ff; border-color:rgba(148,197,218,.22); box-shadow:inset 0 1px 0 rgba(255,255,255,.06), 0 8px 18px rgba(0,0,0,.18); }
        html.theme-dark input[readonly] { background:rgba(7,17,31,.50); color:#c7d9e8; }
        html.theme-dark input:focus, html.theme-dark select:focus, html.theme-dark textarea:focus { border-color:rgba(103,190,217,.72); box-shadow:inset 0 1px 0 rgba(255,255,255,.08), 0 0 0 3px rgba(103,190,217,.16), 0 10px 24px rgba(0,0,0,.22); }
        html.theme-dark .exchange-box, html.theme-dark .theme-switch-row, html.theme-dark .selection-option, html.theme-dark .mini { background:rgba(8,19,34,.58); border-color:rgba(148,197,218,.22); color:#dbeafe; box-shadow:inset 0 1px 0 rgba(255,255,255,.06), 0 10px 24px rgba(0,0,0,.18); }
        html.theme-dark .theme-switch-row span, html.theme-dark .selection-option strong { color:#dbeafe; }
        html.theme-dark .selection-option:has(input:checked) { background:rgba(13,35,56,.86); border-color:rgba(103,190,217,.72); box-shadow:0 16px 34px rgba(0,0,0,.24); }
        html.theme-dark .glass-dock { background:rgba(8,19,34,.70); border-color:rgba(148,197,218,.20); box-shadow:0 18px 46px rgba(0,0,0,.26); }
        html.theme-dark .glass-dock a, html.theme-dark .glass-dock button { color:#dbeafe; background:rgba(13,25,42,.66); border-color:rgba(148,197,218,.18); box-shadow:inset 0 1px 0 rgba(255,255,255,.06), 0 8px 18px rgba(0,0,0,.18); }
        html.theme-dark .glass-dock a.active, html.theme-dark .glass-dock a:hover, html.theme-dark .glass-dock button:hover { background:rgba(103,190,217,.18); border-color:rgba(103,190,217,.48); }
        html.theme-dark .glass-dock .dock-trigger.active { background:rgba(103,190,217,.18); border-color:rgba(103,190,217,.48); }
        html.theme-dark .glass-dock .dock-menu { background:rgba(8,19,34,.92); border-color:rgba(148,197,218,.22); box-shadow:0 18px 42px rgba(0,0,0,.38); }
        html.theme-dark .card { background:var(--panel); border-color:rgba(148,197,218,.18); box-shadow:inset 0 1px 0 rgba(255,255,255,.05), var(--shadow); }
        html.theme-dark .card .field { background:rgba(8,19,34,.38); border-color:rgba(148,197,218,.14); box-shadow:inset 0 1px 0 rgba(255,255,255,.04); }
        html.theme-dark th, html.theme-dark td { border-bottom-color:rgba(148,197,218,.14); color:#dbeafe; }
        html.theme-dark th { color:#bcecff; }
        html.theme-dark tr:nth-child(even) td { background:rgba(8,19,34,.22); }
        html.theme-dark table a:not(.btn) { color:#bcecff; }
        html.theme-dark #items thead th, html.theme-dark #items tbody td, html.theme-dark #items tfoot th { background:rgba(8,19,34,.58); }
        html.theme-dark #items tbody tr:hover td { background:rgba(17,43,65,.78); }
        html.theme-dark .alert-item { background:rgba(8,19,34,.58); border-color:rgba(148,197,218,.16); box-shadow:inset 0 1px 0 rgba(255,255,255,.05), 0 8px 18px rgba(0,0,0,.16); }
        html.theme-dark .btn.secondary { background:rgba(8,19,34,.78); color:#bcecff; border-color:rgba(103,190,217,.34); }
        html.theme-dark .dash-actions a.secondary { background:rgba(8,19,34,.78); color:#bcecff; border-color:rgba(103,190,217,.34); }
        html.theme-dark .btn.danger { background:#991b1b; border-color:#dc2626; color:#fff; }
        html.theme-dark .btn.force-danger { background:#641313; border-color:#dc2626; color:#fff; }
        html.theme-dark .notice { background:rgba(20,83,45,.32); border-color:rgba(52,211,153,.32); color:#bbf7d0; }
        html.theme-dark .errors { background:rgba(127,29,29,.34); border-color:rgba(248,113,113,.34); color:#fecaca; }
        html.theme-dark .force-delete { background:rgba(127,29,29,.24); border-color:rgba(248,113,113,.28); }
        html.theme-dark .force-delete summary { color:#fecaca; }
        html.theme-dark .stat, html.theme-dark h1, html.theme-dark h2, html.theme-dark label { color:#e6f2ff; }
        html.theme-dark .badge, html.theme-dark .pill { background:rgba(103,190,217,.16); color:#bcecff; }
        html.theme-dark nav[role="navigation"] { color:#dbeafe; }
        html.theme-dark nav[role="navigation"] a, html.theme-dark nav[role="navigation"] span[aria-current] span, html.theme-dark nav[role="navigation"] span[aria-disabled="true"] span { background:rgba(8,19,34,.70); border-color:rgba(148,197,218,.18); box-shadow:inset 0 1px 0 rgba(255,255,255,.05), 0 8px 18px rgba(0,0,0,.16); }
        @media (max-width: 920px) { .dash-shell { grid-template-columns:1fr; } .cards, .dashboard-grid, .dashboard-bottom, .kpi-strip, .focus-card, .monthly-kpis, .selection-options, .grid.cols-2, .grid.cols-4, .row { grid-template-columns:1fr; } .monthly-summary-head { flex-direction:column; } .monthly-status { min-width:0; width:100%; } .dash-top, .dash-actions, .top { align-items:stretch; flex-direction:column; } .commercial-select { justify-content:space-between; } .content { padding:18px; } .glass-dock { width:auto; flex-wrap:wrap; } .glass-dock .dock-menu { position:static; min-width:100%; width:100%; margin-top:6px; } }
        @media (max-width: 640px) { .login-shell { padding:18px; display:flex; flex-wrap:wrap; align-content:center; justify-content:center; } .login-shell > .login-logo { position:static; width:150px; height:68px; margin:0 5px 12px; } .glass-login .login-logo { display:none; } .glass-form { order:2; padding:28px; } }
        @media print { .glass-dock, .actions, .no-print { display:none !important; } body { background:#fff; } }
    </style>
    @stack('styles')
</head>
<body class="theme-glass">
@if(auth()->check() && ! request()->routeIs('dashboard') && ! request()->routeIs('commercial.select'))
    <section class="dash-shell">
        @include('partials.navigation')
        <main class="content">
            @include('partials.flash')
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </section>
@else
    @include('partials.flash')
    {{ $slot ?? '' }}
    @yield('content')
@endif
<div id="global-glass-confirm" class="glass-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="global-glass-confirm-title">
    <div class="glass-modal">
        <h2 id="global-glass-confirm-title">Confirmar acción</h2>
        <p id="global-glass-confirm-message"></p>
        <div class="glass-modal-actions">
            <button type="button" class="btn secondary" id="global-glass-confirm-cancel">Cancelar</button>
            <button type="button" class="btn" id="global-glass-confirm-ok">Continuar</button>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    let pendingGlassForm = null;
    const confirmModal = document.getElementById('global-glass-confirm');
    const confirmMessage = document.getElementById('global-glass-confirm-message');
    const confirmOk = document.getElementById('global-glass-confirm-ok');
    const confirmCancel = document.getElementById('global-glass-confirm-cancel');
    const themeToggles = document.querySelectorAll('[data-theme-toggle]');
    const isDark = localStorage.getItem('theme-mode') === 'dark';

    document.documentElement.classList.toggle('theme-dark', isDark);
    themeToggles.forEach(function (toggle) {
        toggle.checked = isDark;
        toggle.addEventListener('change', function () {
            const enabled = toggle.checked;
            document.documentElement.classList.toggle('theme-dark', enabled);
            localStorage.setItem('theme-mode', enabled ? 'dark' : 'light');
            themeToggles.forEach(function (otherToggle) {
                otherToggle.checked = enabled;
            });
        });
    });

    document.querySelectorAll('[data-glass-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            const modal = button.closest('.glass-modal-backdrop');
            if (modal) {
                modal.classList.remove('is-open');
                showNextDashboardAlert(modal);
            }
        });
    });

    const dashboardAlerts = Array.from(document.querySelectorAll('[data-dashboard-alert]'));

    function showNextDashboardAlert(currentModal) {
        const currentIndex = dashboardAlerts.indexOf(currentModal);
        const nextModal = dashboardAlerts.find(function (modal, index) {
            return index > currentIndex && modal.dataset.alertViewed !== '1';
        });

        if (nextModal) {
            nextModal.dataset.alertViewed = '1';
            nextModal.classList.add('is-open');
        }
    }

    dashboardAlerts.forEach(function (modal, index) {
        if (index === 0) {
            modal.dataset.alertViewed = '1';
            modal.classList.add('is-open');
            return;
        }

        modal.classList.remove('is-open');
    });

    document.querySelectorAll('form[data-glass-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.glassConfirmed === '1') {
                return;
            }

            event.preventDefault();
            pendingGlassForm = form;
            confirmMessage.textContent = form.dataset.glassConfirm || '¿Desea continuar con esta acción?';
            confirmModal.classList.add('is-open');
        });
    });

    confirmCancel.addEventListener('click', function () {
        pendingGlassForm = null;
        confirmModal.classList.remove('is-open');
    });

    confirmOk.addEventListener('click', function () {
        if (! pendingGlassForm) {
            confirmModal.classList.remove('is-open');
            return;
        }

        pendingGlassForm.dataset.glassConfirmed = '1';
        confirmModal.classList.remove('is-open');
        pendingGlassForm.submit();
    });
});
</script>
@stack('scripts')
</body>
</html>
