@php
$styles = [
    'violet-lab' => [
        'name' => 'Violeta laboratorio',
        'class' => 'theme-violet',
        'brand' => 'San Benjamin',
        'accent' => 'Supra Medic',
        'note' => 'Acceso privado al sistema',
    ],
    'botanical-clean' => [
        'name' => 'Natural limpio',
        'class' => 'theme-green',
        'brand' => 'San Benjamin',
        'accent' => 'Supra Medic',
        'note' => 'Acceso privado al sistema',
    ],
    'blue-depth' => [
        'name' => 'Azul profundo',
        'class' => 'theme-depth',
        'brand' => 'San Benjamin',
        'accent' => 'Supra Medic',
        'note' => 'Acceso privado al sistema',
    ],
    'commerce-minimal' => [
        'name' => 'Comercio minimal',
        'class' => 'theme-commerce',
        'brand' => 'San Benjamin',
        'accent' => 'Supra Medic',
        'note' => 'Acceso privado al sistema',
    ],
    'clinic-premium' => [
        'name' => 'Clinica premium',
        'class' => 'theme-clinic',
        'brand' => 'San Benjamin',
        'accent' => 'Supra Medic',
        'note' => 'Acceso privado al sistema',
    ],
    'operator-pos' => [
        'name' => 'Caja operador',
        'class' => 'theme-pos',
        'brand' => 'San Benjamin',
        'accent' => 'Supra Medic',
        'note' => 'Acceso privado al sistema',
    ],
    'erp-modern' => [
        'name' => 'ERP moderno',
        'class' => 'theme-erp',
        'brand' => 'San Benjamin',
        'accent' => 'Supra Medic',
        'note' => 'Acceso privado al sistema',
    ],
    'glass-clear' => [
        'name' => 'Glass claro',
        'class' => 'theme-glass',
        'brand' => 'San Benjamin',
        'accent' => 'Supra Medic',
        'note' => 'Acceso privado al sistema',
    ],
];
$theme = $styles[$style];
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $theme['name'] }} - {{ $screen }}</title>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family: Arial, Helvetica, sans-serif; color:#172033; background:#f6f8fc; letter-spacing:0; }
        a { color:inherit; text-decoration:none; }
        .preview-nav { position:fixed; z-index:30; top:14px; left:14px; display:flex; gap:8px; }
        .preview-nav a { background:rgba(255,255,255,.92); border:1px solid rgba(15,23,42,.12); padding:8px 10px; border-radius:6px; font-size:12px; box-shadow:0 8px 22px rgba(15,23,42,.08); }
        input, button, select { font:inherit; }
        .login-shell { min-height:100vh; display:grid; place-items:center; padding:38px; position:relative; overflow:hidden; }
        .login-card { width:min(900px, 100%); min-height:560px; display:grid; grid-template-columns:1.05fr .95fr; overflow:hidden; }
        .login-form { padding:56px; display:flex; flex-direction:column; justify-content:center; }
        .login-form h1 { margin:0 0 8px; font-size:28px; }
        .login-form p { margin:0 0 26px; color:var(--muted); }
        .field { margin-bottom:13px; }
        label { display:block; font-size:12px; font-weight:700; margin-bottom:6px; }
        input, select { width:100%; border:1px solid var(--line); border-radius:6px; padding:11px 12px; background:#fff; color:#172033; }
        button { width:100%; border:0; border-radius:6px; padding:12px; background:var(--primary); color:#fff; font-weight:700; cursor:pointer; }
        .split-art { position:relative; min-height:360px; overflow:hidden; }
        .brand-mark { width:46px; height:46px; display:grid; place-items:center; border-radius:7px; color:#fff; background:var(--primary); font-weight:800; margin-bottom:18px; }
        .green-login-wrap { width:min(1040px, 100%); min-height:600px; display:grid; grid-template-columns:410px 1fr; gap:34px; align-items:center; }
        .green-login-form { background:#fff; border:1px solid #dfe7dc; border-radius:8px; padding:46px; box-shadow:0 18px 44px rgba(33,59,37,.10); }
        .green-login-visual { min-height:600px; position:relative; overflow:hidden; border-radius:0 8px 8px 0; background:#f3f8ef; }
        .green-login-visual:before { content:""; position:absolute; width:520px; height:310px; right:-60px; top:50px; border-radius:48%; background:#2f7d32; transform:rotate(-20deg); box-shadow:-135px 140px 0 #4e934b, -300px 52px 0 #83b87b, 60px 300px 0 #1f5f28; }
        .green-login-visual:after { content:""; position:absolute; inset:auto 70px 70px auto; width:240px; height:2px; background:#c9d8c4; transform:rotate(-28deg); box-shadow:-80px -120px 0 #c9d8c4, -180px 80px 0 #c9d8c4; }
        .dash-shell { min-height:100vh; display:grid; grid-template-columns:248px 1fr; background:var(--dash-bg); }
        .side { padding:24px 16px; background:var(--side); color:var(--side-text); }
        .side .brand { font-size:19px; font-weight:800; margin-bottom:22px; }
        .side a { display:block; padding:10px 12px; border-radius:6px; color:inherit; opacity:.86; }
        .side a.active, .side a:hover { background:var(--side-active); opacity:1; }
        .content { padding:28px; }
        .dash-top { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:20px; }
        .dash-top h1 { margin:0; font-size:26px; }
        .dash-actions { display:flex; gap:10px; }
        .dash-actions a { padding:10px 12px; border-radius:6px; background:var(--primary); color:#fff; font-size:13px; }
        .commercial-select { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:8px; background:var(--panel); border:1px solid var(--line); box-shadow:var(--shadow); }
        .commercial-select span { font-size:12px; color:var(--muted); font-weight:700; }
        .commercial-select select { width:auto; min-width:150px; padding:7px 9px; }
        .cards { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:14px; margin-bottom:16px; }
        .metric, .panel { background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:16px; box-shadow:var(--shadow); }
        .metric span { color:var(--muted); font-size:12px; }
        .metric strong { display:block; margin-top:8px; font-size:25px; }
        .dashboard-grid { display:grid; grid-template-columns:1.15fr .85fr; gap:16px; }
        .dashboard-grid.equal { grid-template-columns:1fr 1fr; }
        .dashboard-grid.three { grid-template-columns:1fr 1fr 1fr; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:10px 6px; border-bottom:1px solid var(--line); text-align:left; font-size:13px; }
        th { color:var(--muted); font-size:11px; text-transform:uppercase; }
        .chart { height:220px; display:flex; align-items:end; gap:12px; padding:18px 4px 0; }
        .bar { flex:1; border-radius:6px 6px 0 0; background:var(--primary); min-height:40px; }
        .bar.alt { background:var(--secondary); }
        .badge { display:inline-block; padding:5px 8px; border-radius:6px; background:var(--soft); color:var(--primary); font-size:12px; font-weight:700; }
        .focus-card { display:grid; grid-template-columns:1fr 160px; gap:16px; align-items:center; }
        .ring { width:142px; height:142px; border-radius:50%; background:conic-gradient(var(--primary) 0 68%, var(--soft) 68% 100%); display:grid; place-items:center; }
        .ring span { width:94px; height:94px; border-radius:50%; background:var(--panel); display:grid; place-items:center; font-size:24px; font-weight:800; }
        .status-list { display:grid; gap:10px; }
        .status-row { display:grid; grid-template-columns:1fr auto; gap:10px; align-items:center; padding:12px; border:1px solid var(--line); border-radius:7px; background:rgba(255,255,255,.58); }
        .progress { height:8px; border-radius:999px; background:var(--soft); overflow:hidden; margin-top:8px; }
        .progress i { display:block; height:100%; background:var(--primary); border-radius:999px; }
        .kpi-strip { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-top:14px; }
        .mini { border:1px solid var(--line); border-radius:7px; padding:12px; background:rgba(255,255,255,.55); }
        .mini strong { display:block; font-size:20px; margin-top:5px; }
        .timeline { display:grid; gap:12px; }
        .timeline-row { display:grid; grid-template-columns:76px 1fr auto; gap:12px; align-items:center; padding-bottom:12px; border-bottom:1px solid var(--line); }
        .pill { display:inline-block; padding:5px 8px; border-radius:999px; background:var(--soft); color:var(--primary); font-size:12px; font-weight:700; }
        .command-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; }
        .command { min-height:112px; border:1px solid var(--line); border-radius:8px; padding:14px; background:rgba(255,255,255,.68); display:flex; flex-direction:column; justify-content:space-between; }
        .command b { font-size:16px; }
        .wide-panel { margin-top:16px; }
        .commerce-board { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
        .ticket { background:#fff; color:#172033; border-radius:8px; padding:14px; border:1px solid rgba(15,23,42,.10); box-shadow:0 14px 30px rgba(20,50,130,.12); }
        .ticket h3 { margin:0 0 10px; font-size:14px; }
        .ticket-line { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #e9edf7; font-size:13px; }
        .depth-module-menu { display:none; position:relative; }
        .depth-module-menu button { width:auto; padding:10px 14px; border:1px solid rgba(255,255,255,.24); background:rgba(255,255,255,.12); color:#fff; }
        .depth-module-menu .menu-list { display:none; position:absolute; right:0; top:46px; min-width:230px; padding:8px; border-radius:8px; background:#063f76; border:1px solid rgba(255,255,255,.18); box-shadow:0 22px 44px rgba(0,30,80,.34); }
        .depth-module-menu:hover .menu-list, .depth-module-menu:focus-within .menu-list { display:block; z-index:20; }
        .depth-module-menu .menu-list a { display:block; padding:10px 12px; border-radius:6px; color:#eaf7ff; }
        .depth-module-menu .menu-list a:hover { background:rgba(255,255,255,.12); }
        .commerce-tabs { margin:0 28px; padding:8px; border-radius:10px; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.25); display:grid; grid-template-columns:repeat(6,1fr); gap:8px; box-shadow:0 18px 40px rgba(18,52,150,.16); }
        .commerce-tabs a { text-align:center; padding:11px 8px; border-radius:8px; background:rgba(255,255,255,.10); color:#fff; font-weight:700; font-size:13px; }
        .commerce-tabs a.active, .commerce-tabs a:hover { background:#fff; color:#2c55d6; }
        .clinic-login { width:min(980px,100%); display:grid; grid-template-columns:1fr 420px; border-radius:8px; overflow:hidden; background:#fff; border:1px solid #d9e2ec; box-shadow:0 22px 60px rgba(25,61,85,.12); }
        .clinic-info { padding:46px; background:linear-gradient(135deg,#eaf6fb,#f8fcff); display:flex; flex-direction:column; justify-content:space-between; }
        .clinic-info h2 { font-size:32px; margin:0; color:#12344d; }
        .clinic-cross { width:92px; height:92px; position:relative; margin:22px 0; }
        .clinic-cross:before, .clinic-cross:after { content:""; position:absolute; background:#2d8cab; border-radius:8px; }
        .clinic-cross:before { width:32px; height:92px; left:30px; }
        .clinic-cross:after { width:92px; height:32px; top:30px; }
        .pos-login { width:min(1020px,100%); display:grid; grid-template-columns:1fr 380px; gap:18px; align-items:stretch; }
        .pos-pad { background:#101828; color:#fff; border-radius:10px; padding:30px; display:grid; grid-template-columns:repeat(2,1fr); gap:14px; }
        .pos-key { border-radius:10px; background:#1f2937; padding:22px; display:flex; flex-direction:column; justify-content:space-between; min-height:120px; border:1px solid #344054; }
        .pos-key strong { font-size:26px; }
        .pos-form { background:#fff; color:#172033; border-radius:10px; padding:38px; box-shadow:0 18px 44px rgba(16,24,40,.14); }
        .erp-login { width:min(1060px,100%); display:grid; grid-template-columns:300px 1fr; min-height:610px; background:#fff; border:1px solid #d0d5dd; border-radius:8px; overflow:hidden; box-shadow:0 18px 48px rgba(16,24,40,.12); }
        .erp-nav-preview { background:#101828; color:#e4e7ec; padding:26px 18px; }
        .erp-nav-preview a { display:block; padding:11px 12px; border-radius:6px; margin-bottom:6px; background:rgba(255,255,255,.04); }
        .erp-form { padding:64px; max-width:520px; }
        .glass-login { width:min(980px,100%); min-height:600px; display:grid; place-items:center; border-radius:18px; background:linear-gradient(135deg,#e6f3ff,#f8fbff 45%,#eef2ff); position:relative; overflow:hidden; }
        .glass-login:before { content:""; position:absolute; width:420px; height:420px; border-radius:50%; background:#a7d8ff; left:-90px; top:-100px; opacity:.55; }
        .glass-login:after { content:""; position:absolute; width:360px; height:360px; border-radius:50%; background:#c7d2fe; right:-80px; bottom:-90px; opacity:.55; }
        .glass-form { width:min(430px,90%); position:relative; z-index:1; padding:42px; border-radius:14px; background:rgba(255,255,255,.72); border:1px solid rgba(255,255,255,.88); box-shadow:0 24px 70px rgba(73,102,140,.18); backdrop-filter:blur(18px); }
        .module-rail { display:grid; gap:10px; margin-top:18px; }
        .module-rail a { padding:12px; border-radius:8px; background:#f1f7fa; color:#12344d; font-weight:700; }
        .pos-actions { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin:0 auto 16px; max-width:1180px; }
        .pos-action { background:linear-gradient(135deg,#fff,#fff7ed); color:#111827; border:1px solid #fed7aa; border-radius:10px; padding:16px; min-height:92px; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 12px 28px rgba(249,115,22,.13); }
        .pos-action b { font-size:17px; }
        .pos-action span { color:#9a3412; font-size:13px; }
        .pos-action:before { content:""; width:34px; height:6px; border-radius:999px; background:#f97316; margin-bottom:14px; }
        .pos-action.primary { background:linear-gradient(135deg,#f97316,#fb923c); color:#fff; border-color:#fb923c; }
        .pos-action.primary span { color:#fff7ed; }
        .pos-action.primary:before { background:#fff; }
        .erp-topbar { background:#fff; border-bottom:1px solid #d0d5dd; padding:14px 26px; display:flex; align-items:center; justify-content:space-between; }
        .erp-menu { display:flex; gap:6px; }
        .erp-menu a { padding:8px 10px; border-radius:6px; color:#344054; font-weight:700; font-size:13px; }
        .erp-menu a.active { background:#eef4ff; color:#175cd3; }
        .glass-dock { position:sticky; top:12px; z-index:12; margin:16px auto 0; width:max-content; max-width:calc(100% - 32px); display:flex; gap:8px; padding:8px; border-radius:14px; background:rgba(255,255,255,.64); border:1px solid rgba(255,255,255,.9); box-shadow:0 18px 46px rgba(73,102,140,.16); backdrop-filter:blur(16px); }
        .glass-dock a { padding:10px 12px; border-radius:10px; color:#1e3a5f; font-weight:700; }
        .glass-dock a.active { background:#fff; }
        .theme-violet { --primary:#6d35f2; --secondary:#151032; --soft:#eee8ff; --muted:#6b6478; --line:#ddd7ec; --panel:#fff; --dash-bg:#f6f3ff; --side:#171033; --side-text:#f2efff; --side-active:#33205f; --shadow:0 14px 34px rgba(55,34,105,.10); }
        .theme-violet .login-shell { background:radial-gradient(circle at 16% 84%,#f0a56f 0 12%,transparent 28%), radial-gradient(circle at 86% 12%,#b9d5ff 0 14%,transparent 30%), #f4f1fb; }
        .theme-violet .login-card { border:4px solid #24135c; border-radius:18px; background:#fff; box-shadow:0 24px 70px rgba(44,26,100,.25); }
        .theme-violet .split-art { background:linear-gradient(135deg, rgba(35,15,96,.90), rgba(126,59,242,.60)), url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='720' height='560' viewBox='0 0 720 560'%3E%3Crect width='720' height='560' fill='%23210d55'/%3E%3Ccircle cx='190' cy='190' r='120' fill='%238b5cf6' opacity='.55'/%3E%3Ccircle cx='420' cy='250' r='160' fill='%2360a5fa' opacity='.42'/%3E%3Cpath d='M145 410 C240 280 320 500 470 300' stroke='%23fff' stroke-width='18' opacity='.35' fill='none'/%3E%3C/svg%3E"); background-size:cover; }
        .theme-green { --primary:#316f35; --secondary:#8aa05b; --soft:#eef6ea; --muted:#687369; --line:#dde7da; --panel:#fff; --dash-bg:#f6f8f3; --side:#213b25; --side-text:#eef7ec; --side-active:#315b37; --shadow:0 12px 28px rgba(37,71,42,.08); }
        .theme-green .login-shell { background:#fbfcf8; }
        .theme-green .login-card { border:1px solid #dfe7dc; border-radius:8px; background:#fff; box-shadow:0 20px 54px rgba(33,59,37,.10); }
        .theme-green .split-art { background:#eef5eb; }
        .theme-green .split-art:before, .theme-green .split-art:after { content:""; position:absolute; border-radius:48% 52% 44% 56%; background:#2f7d32; transform:rotate(-18deg); }
        .theme-green .split-art:before { width:360px; height:210px; right:-70px; top:38px; box-shadow:-80px 120px 0 #4e934b, -180px 28px 0 #76a86f; }
        .theme-green .split-art:after { width:260px; height:150px; right:110px; bottom:56px; background:#1f5f28; box-shadow:150px -34px 0 #7cab73; }
        .theme-green .dash-shell { display:block; }
        .theme-green .side { display:flex; align-items:center; gap:8px; padding:16px 28px; background:#fff; color:#213b25; border-bottom:1px solid #dde7da; }
        .theme-green .side .brand { margin:0 18px 0 0; color:#213b25; }
        .theme-green .side a { width:auto; padding:8px 10px; }
        .theme-green .side a.active, .theme-green .side a:hover { background:#eef6ea; color:#316f35; }
        .theme-depth { --primary:#026ab6; --secondary:#52d1f6; --soft:#dff5ff; --muted:#b8d8ee; --line:rgba(255,255,255,.18); --panel:rgba(5,70,135,.72); --dash-bg:#04589f; --side:#053f76; --side-text:#eaf7ff; --side-active:rgba(255,255,255,.13); --shadow:0 20px 46px rgba(0,30,80,.20); color:#fff; }
        .theme-depth .login-shell { background:linear-gradient(135deg,#064783,#0479c8); color:#fff; }
        .theme-depth .login-card { display:block; width:min(620px,100%); min-height:auto; border-radius:8px; background:rgba(3,66,127,.66); box-shadow:0 24px 70px rgba(0,25,70,.26); position:relative; }
        .theme-depth .login-card:before { content:""; position:absolute; inset:-90px; background:radial-gradient(circle at 22% 30%,rgba(100,221,255,.28),transparent 18%), radial-gradient(circle at 82% 76%,rgba(255,255,255,.20),transparent 14%); pointer-events:none; }
        .theme-depth .login-form { width:360px; margin:0 auto; position:relative; }
        .theme-depth input { background:#fff; border-color:#b7dfff; }
        .theme-depth .side, .theme-depth .metric, .theme-depth .panel { backdrop-filter:blur(10px); }
        .theme-depth .dash-shell { grid-template-columns:92px 1fr; }
        .theme-depth .side { padding:22px 12px; display:flex; flex-direction:column; align-items:center; gap:10px; }
        .theme-depth .side .brand { width:52px; height:52px; display:grid; place-items:center; margin:0 0 14px; border-radius:8px; background:rgba(255,255,255,.14); font-size:14px; text-align:center; }
        .theme-depth .side a { width:52px; height:52px; padding:0; display:grid; place-items:center; font-size:0; border-radius:8px; }
        .theme-depth .side a::first-letter { font-size:16px; }
        .theme-depth .side a.active, .theme-depth .side a:hover { background:rgba(255,255,255,.18); }
        .theme-depth .depth-module-menu { display:block; }
        .theme-depth .commercial-select { background:rgba(255,255,255,.12); color:#fff; }
        .theme-depth .commercial-select select { background:#fff; color:#172033; }
        .theme-depth .dash-top { display:block; }
        .theme-depth .dash-actions { align-items:center; justify-content:flex-end; margin-top:12px; }
        .theme-commerce { --primary:#2c55d6; --secondary:#88a5ff; --soft:#e9eeff; --muted:#d9e2ff; --line:rgba(255,255,255,.28); --panel:rgba(255,255,255,.92); --dash-bg:#2c55d6; --side:#2447bb; --side-text:#fff; --side-active:rgba(255,255,255,.16); --shadow:0 18px 44px rgba(18,52,150,.20); color:#fff; }
        .theme-commerce .login-shell { background:#2d55d7; color:#fff; }
        .theme-commerce .login-shell:before { content:""; position:absolute; width:720px; height:520px; background:rgba(255,255,255,.06); border-radius:42%; top:-190px; right:120px; transform:rotate(18deg); }
        .theme-commerce .login-card { display:block; width:min(430px,100%); min-height:auto; background:transparent; position:relative; }
        .theme-commerce .brand-mark { margin:0 auto 22px; background:transparent; border:2px solid #fff; font-size:30px; }
        .theme-commerce input { background:transparent; border-color:rgba(255,255,255,.55); color:#fff; }
        .theme-commerce input::placeholder { color:#dbe5ff; }
        .theme-commerce button { background:#fff; color:#2c55d6; }
        .theme-commerce .panel, .theme-commerce .metric { color:#172033; }
        .theme-commerce .dash-shell { display:block; }
        .theme-commerce .side { background:transparent; color:#fff; padding:22px 28px 12px; display:block; }
        .theme-commerce .side .brand { margin:0; font-size:22px; }
        .theme-commerce .side > a { display:none; }
        .theme-commerce .commercial-select { background:rgba(255,255,255,.92); color:#172033; }
        .theme-commerce .content { padding-top:22px; }
        .theme-clinic { --primary:#2d8cab; --secondary:#12344d; --soft:#e8f5f9; --muted:#617381; --line:#d9e2ec; --panel:#fff; --dash-bg:#f5f9fc; --side:#fff; --side-text:#12344d; --side-active:#e8f5f9; --shadow:0 12px 30px rgba(25,61,85,.08); }
        .theme-clinic .login-shell { background:#f5f9fc; }
        .theme-clinic .side { border-right:1px solid #d9e2ec; }
        .theme-clinic .side a { color:#12344d; }
        .theme-clinic .commercial-select { background:#fff; }
        .theme-pos { --primary:#f97316; --secondary:#111827; --soft:#fff3e8; --muted:#667085; --line:#e4e7ec; --panel:#fff; --dash-bg:#f7f8fa; --side:#111827; --side-text:#fff; --side-active:#f97316; --shadow:0 12px 28px rgba(16,24,40,.08); }
        .theme-pos .login-shell { background:#f3f4f6; }
        .theme-pos .dash-shell { display:block; }
        .theme-pos .side { display:none; }
        .theme-pos .content { max-width:1180px; margin:0 auto; }
        .theme-erp { --primary:#175cd3; --secondary:#344054; --soft:#eef4ff; --muted:#667085; --line:#d0d5dd; --panel:#fff; --dash-bg:#f2f4f7; --side:#101828; --side-text:#e4e7ec; --side-active:#1d2939; --shadow:none; }
        .theme-erp .login-shell { background:#eaecf0; }
        .theme-erp .dash-shell { display:block; }
        .theme-erp .side { display:none; }
        .theme-erp .content { padding-top:18px; }
        .theme-glass { --primary:#2563eb; --secondary:#7c3aed; --soft:#eaf2ff; --muted:#55708c; --line:rgba(255,255,255,.76); --panel:rgba(255,255,255,.72); --dash-bg:linear-gradient(135deg,#e8f4ff,#f8fbff 42%,#eef2ff); --side:#fff; --side-text:#1e3a5f; --side-active:#fff; --shadow:0 18px 46px rgba(73,102,140,.14); }
        .theme-glass .login-shell { background:linear-gradient(135deg,#e8f4ff,#f8fbff 42%,#eef2ff); }
        .theme-glass .dash-shell { display:block; }
        .theme-glass .side { display:none; }
        .theme-glass .panel, .theme-glass .metric, .theme-glass .commercial-select { backdrop-filter:blur(16px); }
        @media (max-width: 920px) { .login-card, .green-login-wrap, .clinic-login, .pos-login, .erp-login { grid-template-columns:1fr; } .split-art, .green-login-visual, .clinic-info, .pos-pad, .erp-nav-preview { display:none; } .dash-shell, .theme-depth .dash-shell { grid-template-columns:1fr; } .cards, .dashboard-grid, .dashboard-grid.equal, .dashboard-grid.three, .commerce-board, .command-grid, .kpi-strip, .focus-card, .commerce-tabs, .pos-actions { grid-template-columns:1fr; } .dash-top, .dash-actions, .erp-topbar { align-items:stretch; flex-direction:column; } .commercial-select { justify-content:space-between; } .side, .theme-green .side, .theme-commerce .side { display:none; } .content { padding:18px; } .glass-dock, .erp-menu { width:auto; flex-wrap:wrap; } }
    </style>
</head>
<body class="{{ $theme['class'] }}">
<nav class="preview-nav">
    <a href="{{ route('design-options.index') }}">Opciones</a>
    <a href="{{ route('design-options.show', [$style, $screen === 'login' ? 'dashboard' : 'login']) }}">Ver {{ $screen === 'login' ? 'dashboard' : 'login' }}</a>
</nav>

@if($screen === 'login')
    <section class="login-shell">
        @if($style === 'botanical-clean')
            <div class="green-login-wrap">
                <form class="green-login-form">
                    <div class="brand-mark">SB</div>
                    <h1>Ingresar al sistema</h1>
                    <p>{{ $theme['note'] }}</p>
                    <div class="field"><label>Usuario</label><input placeholder="correo@empresa.com" value=""></div>
                    <div class="field"><label>Clave</label><input type="password" placeholder="********"></div>
                    <button type="button">Entrar</button>
                </form>
                <div class="green-login-visual"></div>
            </div>
        @elseif($style === 'clinic-premium')
            <div class="clinic-login">
                <div class="clinic-info">
                    <div>
                        <div class="clinic-cross"></div>
                        <h2>Acceso seguro para operacion privada</h2>
                    </div>
                    <p>Ingreso protegido para personal autorizado de San Benjamin y Supra Medic.</p>
                </div>
                <form class="login-form">
                    <div class="brand-mark">SB</div>
                    <h1>Acceso seguro</h1>
                    <p>{{ $theme['note'] }}</p>
                    <div class="field"><label>Usuario</label><input placeholder="correo@empresa.com" value=""></div>
                    <div class="field"><label>Clave</label><input type="password" placeholder="********"></div>
                    <button type="button">Entrar</button>
                </form>
            </div>
        @elseif($style === 'operator-pos')
            <div class="pos-login">
                <div class="pos-pad">
                    <div class="pos-key"><span>Turno</span><strong>08:30</strong></div>
                    <div class="pos-key"><span>Usuario</span><strong>OP</strong></div>
                    <div class="pos-key"><span>Acceso</span><strong>PIN</strong></div>
                    <div class="pos-key"><span>Estado</span><strong>OK</strong></div>
                </div>
                <form class="pos-form">
                    <div class="brand-mark">SB</div>
                    <h1>Inicio de caja</h1>
                    <p>{{ $theme['note'] }}</p>
                    <div class="field"><label>Usuario</label><input placeholder="operador@empresa.com" value=""></div>
                    <div class="field"><label>Clave</label><input type="password" placeholder="********"></div>
                    <button type="button">Entrar</button>
                </form>
            </div>
        @elseif($style === 'erp-modern')
            <div class="erp-login">
                <aside class="erp-nav-preview">
                    <div class="brand" style="margin-bottom:22px;font-weight:800;">San Benjamin ERP</div>
                    <p style="color:#98a2b3;line-height:1.55;margin:0;">Acceso administrativo privado para operadores autorizados.</p>
                    <div style="margin-top:28px;padding:14px;border:1px solid #344054;border-radius:8px;background:#1d2939;">
                        <strong>Sesion segura</strong>
                        <p style="color:#98a2b3;margin:8px 0 0;">Usuario, clave y permisos validados.</p>
                    </div>
                </aside>
                <form class="erp-form">
                    <div class="brand-mark">SB</div>
                    <h1>Panel administrativo</h1>
                    <p>{{ $theme['note'] }}</p>
                    <div class="field"><label>Usuario</label><input placeholder="admin@empresa.com" value=""></div>
                    <div class="field"><label>Clave</label><input type="password" placeholder="********"></div>
                    <button type="button">Entrar</button>
                </form>
            </div>
        @elseif($style === 'glass-clear')
            <div class="glass-login">
                <form class="glass-form">
                    <div class="brand-mark">SB</div>
                    <h1>Bienvenido</h1>
                    <p>{{ $theme['note'] }}</p>
                    <div class="field"><label>Usuario</label><input placeholder="correo@empresa.com" value=""></div>
                    <div class="field"><label>Clave</label><input type="password" placeholder="********"></div>
                    <button type="button">Entrar</button>
                </form>
            </div>
        @else
            <div class="login-card">
                @if($style !== 'blue-depth' && $style !== 'commerce-minimal')
                    <div class="split-art"></div>
                @endif
                <form class="login-form">
                    <div class="brand-mark">{{ $style === 'commerce-minimal' ? 'SM' : 'SB' }}</div>
                    <h1>Bienvenido</h1>
                    <p>{{ $theme['note'] }}</p>
                    <div class="field"><label>Usuario</label><input placeholder="correo@empresa.com" value=""></div>
                    <div class="field"><label>Clave</label><input type="password" placeholder="********"></div>
                    <button type="button">Entrar</button>
                </form>
            </div>
        @endif
    </section>
@else
    <section class="dash-shell">
        @if($style === 'erp-modern')
            <header class="erp-topbar">
                <strong>San Benjamin ERP</strong>
                <nav class="erp-menu"><a class="active">Dashboard</a><a>Ventas</a><a>Compras</a><a>Inventario</a><a>Reportes</a><a>Configuracion</a></nav>
            </header>
        @elseif($style === 'glass-clear')
            <nav class="glass-dock"><a class="active">Dashboard</a><a>Ventas</a><a>Compras</a><a>Inventario</a><a>Reportes</a><a>PDF</a></nav>
        @else
            <aside class="side">
                <div class="brand">{{ $theme['brand'] }}</div>
                <a class="active">Dashboard</a><a>Ventas</a><a>Compras</a><a>Inventario</a><a>Reportes</a><a>Comprobantes</a>
                @if($style === 'clinic-premium')
                    <div class="module-rail"><a>Stock critico</a><a>Comprobantes</a><a>Proveedores</a></div>
                @endif
            </aside>
        @endif
        @if($style === 'commerce-minimal')
            <nav class="commerce-tabs">
                <a class="active">Dashboard</a><a>Ventas</a><a>Compras</a><a>Inventario</a><a>Reportes</a><a>Comprobantes</a>
            </nav>
        @endif
        @if($style === 'operator-pos')
            <nav class="pos-actions" style="padding:22px 28px 0;">
                <a class="pos-action primary"><b>Nueva venta</b><span>Registrar salida</span></a>
                <a class="pos-action"><b>Nueva compra</b><span>Registrar entrada</span></a>
                <a class="pos-action"><b>Comprobante</b><span>Imprimir PDF</span></a>
                <a class="pos-action"><b>Stock</b><span>Consultar rapido</span></a>
            </nav>
        @endif
        <main class="content">
            <div class="dash-top">
                <div><h1>Dashboard</h1><div class="badge">{{ $theme['brand'] }} + {{ $theme['accent'] }}</div></div>
                <div class="dash-actions">
                    <div class="commercial-select">
                        <span>Nombre comercial</span>
                        <select><option>San Benjamin</option><option>Supra Medic</option></select>
                    </div>
                    @if($style === 'blue-depth')
                        <div class="depth-module-menu">
                            <button type="button">Modulos</button>
                            <div class="menu-list">
                                <a>Dashboard</a><a>Ventas</a><a>Compras</a><a>Inventario</a><a>Reportes</a><a>Comprobantes</a>
                            </div>
                        </div>
                    @endif
                    <a>Nueva venta</a><a>Nueva compra</a>
                </div>
            </div>
            <div class="cards">
                <div class="metric"><span>Ventas del dia</span><strong>18</strong></div>
                <div class="metric"><span>Total vendido</span><strong>Bs 8,420</strong></div>
                <div class="metric"><span>Stock bajo</span><strong>7</strong></div>
                <div class="metric"><span>Movimientos</span><strong>42</strong></div>
            </div>
            @if($style === 'clinic-premium')
                <div class="dashboard-grid">
                    <section class="panel">
                        <h2>Prioridad clinica/comercial</h2>
                        <div class="status-list">
                            <div class="status-row"><div><b>Reactivos por reponer</b><div class="progress"><i style="width:62%"></i></div></div><span>5</span></div>
                            <div class="status-row"><div><b>Comprobantes para validar</b><div class="progress"><i style="width:34%"></i></div></div><span>3</span></div>
                            <div class="status-row"><div><b>Compras con factura</b><div class="progress"><i style="width:78%"></i></div></div><span>12</span></div>
                        </div>
                    </section>
                    <section class="panel">
                        <h2>Nombres comerciales</h2>
                        <table>
                            <tr><th>Nombre</th><th>Ventas</th><th>Resultado</th></tr>
                            <tr><td>San Benjamin</td><td>Bs 5,980</td><td>Bs 2,860</td></tr>
                            <tr><td>Supra Medic</td><td>Bs 2,440</td><td>Bs 1,350</td></tr>
                        </table>
                    </section>
                </div>
                <section class="panel wide-panel">
                    <h2>Trazabilidad reciente</h2>
                    <table><tr><th>Hora</th><th>Operacion</th><th>Producto</th><th>Usuario</th><th>Estado</th></tr>
                        <tr><td>09:12</td><td>Venta</td><td>Kit diagnostico</td><td>Operador</td><td><span class="pill">Registrada</span></td></tr>
                        <tr><td>10:04</td><td>Compra</td><td>Reactivo</td><td>Administrador</td><td><span class="pill">En stock</span></td></tr>
                        <tr><td>11:30</td><td>PDF</td><td>Comprobante</td><td>Operador</td><td><span class="pill">Impreso</span></td></tr>
                    </table>
                </section>
            @elseif($style === 'operator-pos')
                <div class="dashboard-grid equal">
                    <section class="panel">
                        <h2>Turno actual</h2>
                        <div class="kpi-strip">
                            <div class="mini"><span>Ventas</span><strong>18</strong></div>
                            <div class="mini"><span>Compras</span><strong>9</strong></div>
                            <div class="mini"><span>PDF</span><strong>14</strong></div>
                        </div>
                        <div class="status-list" style="margin-top:14px;">
                            <div class="status-row"><div><b>Proxima accion</b><span>Registrar venta pendiente</span></div><span class="pill">Ahora</span></div>
                            <div class="status-row"><div><b>Stock a revisar</b><span>7 productos bajo minimo</span></div><span class="pill">Stock</span></div>
                        </div>
                    </section>
                    <section class="panel">
                        <h2>Operacion rapida</h2>
                        <table><tr><th>Accion</th><th>Nombre</th><th>Total</th></tr>
                            <tr><td>Venta</td><td>San Benjamin</td><td>Bs 1,240</td></tr>
                            <tr><td>Venta</td><td>Supra Medic</td><td>Bs 520</td></tr>
                            <tr><td>Compra</td><td>San Benjamin</td><td>Bs 860</td></tr>
                            <tr><td>Descuento</td><td>Operador</td><td>12%</td></tr>
                        </table>
                    </section>
                </div>
            @elseif($style === 'erp-modern')
                <div class="dashboard-grid three">
                    <section class="panel">
                        <h2>Finanzas</h2>
                        <table><tr><th>Indicador</th><th>Valor</th></tr><tr><td>Ventas</td><td>Bs 8,420</td></tr><tr><td>Compras</td><td>Bs 4,210</td></tr><tr><td>Resultado</td><td>Bs 4,210</td></tr></table>
                    </section>
                    <section class="panel">
                        <h2>Inventario</h2>
                        <table><tr><th>Estado</th><th>Cantidad</th></tr><tr><td>Stock bajo</td><td>7</td></tr><tr><td>Entradas</td><td>24</td></tr><tr><td>Salidas</td><td>18</td></tr></table>
                    </section>
                    <section class="panel">
                        <h2>Documentos</h2>
                        <table><tr><th>Tipo</th><th>Estado</th></tr><tr><td>Venta SB</td><td>Validado</td></tr><tr><td>Venta SM</td><td>Validado</td></tr><tr><td>Compra</td><td>Pendiente</td></tr></table>
                    </section>
                </div>
                <section class="panel wide-panel">
                    <h2>Registro operacional</h2>
                    <table><tr><th>Modulo</th><th>Ultimo movimiento</th><th>Responsable</th><th>Hora</th></tr><tr><td>Ventas</td><td>N-582</td><td>Operador</td><td>09:48</td></tr><tr><td>Compras</td><td>C-231</td><td>Admin</td><td>10:10</td></tr><tr><td>Inventario</td><td>Ajuste positivo</td><td>Admin</td><td>11:02</td></tr></table>
                </section>
            @elseif($style === 'glass-clear')
                <div class="dashboard-grid">
                    <section class="panel focus-card">
                        <div>
                            <h2>Movimiento mensual</h2>
                            <p>Vista luminosa con filtros visibles y capas suaves para destacar lo importante.</p>
                            <div class="kpi-strip">
                                <div class="mini"><span>San Benjamin</span><strong>Bs 5,980</strong></div>
                                <div class="mini"><span>Supra Medic</span><strong>Bs 2,440</strong></div>
                                <div class="mini"><span>Total</span><strong>Bs 8,420</strong></div>
                            </div>
                        </div>
                        <div class="ring"><span>74%</span></div>
                    </section>
                    <section class="panel">
                        <h2>Actividad</h2>
                        <div class="timeline">
                            <div class="timeline-row"><span>09:20</span><div><b>Venta registrada</b><br><span class="muted">San Benjamin</span></div><span class="pill">PDF</span></div>
                            <div class="timeline-row"><span>10:40</span><div><b>Compra ingresada</b><br><span class="muted">Supra Medic</span></div><span class="pill">Stock</span></div>
                            <div class="timeline-row"><span>11:15</span><div><b>Descuento manual</b><br><span class="muted">Operador</span></div><span class="pill">12%</span></div>
                        </div>
                    </section>
                </div>
            @elseif($style === 'violet-lab')
                <div class="dashboard-grid">
                    <section class="panel focus-card">
                        <div>
                            <h2>Resultado mensual</h2>
                            <p>Vista analitica para comparar San Benjamin y Supra Medic con foco en margen.</p>
                            <div class="kpi-strip">
                                <div class="mini"><span>San Benjamin</span><strong>Bs 5,980</strong></div>
                                <div class="mini"><span>Supra Medic</span><strong>Bs 2,440</strong></div>
                                <div class="mini"><span>% operador</span><strong>18%</strong></div>
                            </div>
                        </div>
                        <div class="ring"><span>68%</span></div>
                    </section>
                    <section class="panel">
                        <h2>Alertas criticas</h2>
                        <div class="status-list">
                            <div class="status-row"><div><b>Stock bajo</b><div class="progress"><i style="width:72%"></i></div></div><span>7</span></div>
                            <div class="status-row"><div><b>Recibos por validar</b><div class="progress"><i style="width:38%"></i></div></div><span>4</span></div>
                            <div class="status-row"><div><b>Compras pendientes</b><div class="progress"><i style="width:54%"></i></div></div><span>11</span></div>
                        </div>
                    </section>
                </div>
                <section class="panel wide-panel">
                    <h2>Movimiento mensual por nombre comercial</h2>
                    <div class="chart">
                        <div class="bar" style="height:82%"></div><div class="bar alt" style="height:42%"></div>
                        <div class="bar" style="height:68%"></div><div class="bar alt" style="height:58%"></div>
                        <div class="bar" style="height:76%"></div><div class="bar alt" style="height:48%"></div>
                        <div class="bar" style="height:90%"></div><div class="bar alt" style="height:62%"></div>
                    </div>
                </section>
            @elseif($style === 'botanical-clean')
                <div class="dashboard-grid equal">
                    <section class="panel">
                        <h2>Agenda operativa</h2>
                        <div class="timeline">
                            <div class="timeline-row"><span>08:30</span><div><b>Revisar compras</b><br><span class="muted">Proveedor ASC</span></div><span class="pill">Compra</span></div>
                            <div class="timeline-row"><span>10:00</span><div><b>Imprimir comprobantes</b><br><span class="muted">San Benjamin</span></div><span class="pill">Venta</span></div>
                            <div class="timeline-row"><span>14:20</span><div><b>Validar stock bajo</b><br><span class="muted">Material laboratorio</span></div><span class="pill">Stock</span></div>
                            <div class="timeline-row"><span>16:45</span><div><b>Cierre diario</b><br><span class="muted">Ambos nombres comerciales</span></div><span class="pill">Caja</span></div>
                        </div>
                    </section>
                    <section class="panel">
                        <h2>Salud del inventario</h2>
                        <div class="status-list">
                            <div class="status-row"><div><b>Kits diagnostico</b><div class="progress"><i style="width:84%"></i></div></div><span>Optimo</span></div>
                            <div class="status-row"><div><b>Material laboratorio</b><div class="progress"><i style="width:42%"></i></div></div><span>Bajo</span></div>
                            <div class="status-row"><div><b>Reactivos</b><div class="progress"><i style="width:66%"></i></div></div><span>Medio</span></div>
                            <div class="status-row"><div><b>Fast Test</b><div class="progress"><i style="width:76%"></i></div></div><span>Bien</span></div>
                        </div>
                    </section>
                </div>
                <section class="panel wide-panel">
                    <h2>Resumen por nombre comercial</h2>
                    <table>
                        <tr><th>Nombre comercial</th><th>Ventas</th><th>Compras</th><th>Resultado</th><th>Estado</th></tr>
                        <tr><td>San Benjamin</td><td>Bs 5,980</td><td>Bs 3,120</td><td>Bs 2,860</td><td><span class="pill">Estable</span></td></tr>
                        <tr><td>Supra Medic</td><td>Bs 2,440</td><td>Bs 1,090</td><td>Bs 1,350</td><td><span class="pill">Creciendo</span></td></tr>
                    </table>
                </section>
            @elseif($style === 'blue-depth')
                <div class="dashboard-grid three">
                    <section class="panel">
                        <h2>Comando rapido</h2>
                        <div class="command-grid">
                            <div class="command"><b>Venta</b><span>Nueva salida</span></div>
                            <div class="command"><b>Compra</b><span>Nueva entrada</span></div>
                            <div class="command"><b>PDF</b><span>Comprobante</span></div>
                            <div class="command"><b>Reporte</b><span>Movimiento</span></div>
                        </div>
                    </section>
                    <section class="panel">
                        <h2>Flujo mensual</h2>
                        <div class="chart">
                            <div class="bar" style="height:55%"></div><div class="bar" style="height:74%"></div><div class="bar" style="height:48%"></div><div class="bar" style="height:88%"></div>
                        </div>
                    </section>
                    <section class="panel">
                        <h2>Operacion activa</h2>
                        <table>
                            <tr><th>Area</th><th>Estado</th></tr>
                            <tr><td>Ventas</td><td>18 registradas</td></tr>
                            <tr><td>Compras</td><td>9 registradas</td></tr>
                            <tr><td>Inventario</td><td>42 movimientos</td></tr>
                            <tr><td>Recibos</td><td>4 pendientes</td></tr>
                        </table>
                    </section>
                </div>
                <section class="panel wide-panel">
                    <h2>Resultado mensual general</h2>
                    <div class="kpi-strip">
                        <div class="mini"><span>Total general</span><strong>Bs 4,210</strong></div>
                        <div class="mini"><span>San Benjamin</span><strong>68%</strong></div>
                        <div class="mini"><span>Supra Medic</span><strong>32%</strong></div>
                    </div>
                </section>
            @else
                <div class="commerce-board">
                    <section class="ticket">
                        <h3>Cola de ventas</h3>
                        <div class="ticket-line"><span>San Benjamin</span><b>Bs 1,240</b></div>
                        <div class="ticket-line"><span>Supra Medic</span><b>Bs 520</b></div>
                        <div class="ticket-line"><span>Descuento operador</span><b>12%</b></div>
                    </section>
                    <section class="ticket">
                        <h3>Compras de hoy</h3>
                        <div class="ticket-line"><span>Facturas</span><b>6</b></div>
                        <div class="ticket-line"><span>Total compras</span><b>Bs 2,180</b></div>
                        <div class="ticket-line"><span>Entradas</span><b>24</b></div>
                    </section>
                    <section class="ticket">
                        <h3>Comprobantes</h3>
                        <div class="ticket-line"><span>Venta SB</span><b>Listo</b></div>
                        <div class="ticket-line"><span>Venta SM</span><b>Listo</b></div>
                        <div class="ticket-line"><span>Compra</span><b>Validar</b></div>
                    </section>
                </div>
                <section class="panel wide-panel">
                    <h2>Operacion compacta</h2>
                    <table>
                        <tr><th>Accion</th><th>Nombre comercial</th><th>Usuario</th><th>Estado</th></tr>
                        <tr><td>Venta</td><td>San Benjamin</td><td>Operador</td><td><span class="pill">Registrada</span></td></tr>
                        <tr><td>Compra</td><td>Supra Medic</td><td>Administrador</td><td><span class="pill">En inventario</span></td></tr>
                        <tr><td>Movimiento mensual</td><td>San Benjamin + Supra Medic</td><td>Operador</td><td><span class="pill">Pendiente</span></td></tr>
                    </table>
                </section>
            @endif
        </main>
    </section>
@endif
</body>
</html>
