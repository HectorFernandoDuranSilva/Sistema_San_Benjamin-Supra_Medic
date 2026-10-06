<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { margin: 0 0 4px; font-size: 18px; }
        .meta { margin-bottom: 12px; color: #444; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 5px; }
        th { background: #e9f3f8; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <h1>Reporte de stock bajo</h1>
    <div class="meta">Generado: {{ $generadoEn->format('d/m/Y H:i') }}</div>
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Categoria</th>
                <th>Stock actual</th>
                <th>Stock minimo</th>
                <th>Diferencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse($productos as $producto)
                <tr>
                    <td>{{ $producto->codigo }}</td>
                    <td>{{ $producto->nombre }}</td>
                    <td>{{ $producto->categoria?->nombre ?? 'Sin categoria' }}</td>
                    <td class="num">{{ number_format($producto->stock_actual, 2) }}</td>
                    <td class="num">{{ number_format($producto->stock_minimo, 2) }}</td>
                    <td class="num">{{ number_format((float) $producto->stock_minimo - $producto->stock_actual, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No hay productos con stock bajo.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>


