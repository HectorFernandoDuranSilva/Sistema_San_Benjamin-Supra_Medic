<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Opciones de estilo</title>
    <style>
        body { margin:0; font-family: Arial, Helvetica, sans-serif; background:#f5f7fb; color:#18212f; }
        main { max-width:1180px; margin:0 auto; padding:32px 20px; }
        h1 { margin:0 0 8px; font-size:28px; }
        p { color:#667085; margin:0 0 24px; }
        .grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:16px; }
        .card { background:#fff; border:1px solid #e4e7ec; border-radius:8px; padding:16px; box-shadow:0 10px 28px rgba(16,24,40,.06); }
        .swatch { height:96px; border-radius:8px; margin-bottom:14px; position:relative; overflow:hidden; }
        .violet { background:linear-gradient(135deg,#34206f,#7c3aed 52%,#f2f0ff); }
        .green { background:linear-gradient(135deg,#f7faf5 0 42%,#2f6b2f 42% 100%); }
        .depth { background:linear-gradient(135deg,#063f86,#0a80d7); }
        .commerce { background:#2d55d7; }
        h2 { font-size:17px; margin:0 0 8px; }
        .links { display:flex; gap:8px; margin-top:16px; }
        a { display:inline-block; padding:8px 10px; border-radius:6px; border:1px solid #d0d5dd; color:#1d4ed8; text-decoration:none; font-size:13px; }
        @media (max-width: 920px) { .grid { grid-template-columns:1fr 1fr; } }
        @media (max-width: 560px) { .grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<main>
    <h1>8 opciones de login y dashboard</h1>
    <p>Son previews aislados. El estilo elegido se aplica despues al sistema real; los otros se eliminan.</p>
    <div class="grid">
        @foreach([
            'violet-lab' => ['Violeta laboratorio', 'Imagen fuerte, panel blanco y acento violeta.'],
            'botanical-clean' => ['Natural limpio', 'Mucho aire, verde sobrio y sensacion ordenada.'],
            'blue-depth' => ['Azul profundo', 'Corporativo moderno, capas y profundidad visual.'],
            'commerce-minimal' => ['Comercio minimal', 'Centrado, azul intenso y enfoque operativo.'],
            'clinic-premium' => ['Clinica premium', 'Serio, claro y confiable para gestion medica/comercial.'],
            'operator-pos' => ['Caja operador', 'Rapido, tactil y enfocado en acciones de venta diaria.'],
            'erp-modern' => ['ERP moderno', 'Denso, ordenado y escalable para muchos modulos.'],
            'glass-clear' => ['Glass claro', 'Moderno, luminoso y con profundidad controlada.'],
        ] as $slug => [$name, $desc])
            <section class="card">
                <div class="swatch {{ ['violet-lab'=>'violet','botanical-clean'=>'green','blue-depth'=>'depth','commerce-minimal'=>'commerce','clinic-premium'=>'green','operator-pos'=>'commerce','erp-modern'=>'depth','glass-clear'=>'violet'][$slug] }}"></div>
                <h2>{{ $name }}</h2>
                <p>{{ $desc }}</p>
                <div class="links">
                    <a href="{{ route('design-options.show', [$slug, 'login']) }}">Ver login</a>
                    <a href="{{ route('design-options.show', [$slug, 'dashboard']) }}">Ver dashboard</a>
                </div>
            </section>
        @endforeach
    </div>
</main>
</body>
</html>
