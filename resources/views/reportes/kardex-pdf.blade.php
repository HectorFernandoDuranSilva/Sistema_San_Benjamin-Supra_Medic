<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px 26px 30px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1b2838; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .header td { border: 0; padding: 0; vertical-align: middle; }
        h1 { margin: 0; font-size: 20px; color: #1e3a5f; }
        .meta { margin-top: 4px; color: #61758a; }
        .badge { text-align: right; font-weight: 700; color: #2388d9; }
        .line { height: 4px; background: #67bed9; border-radius: 99px; margin-bottom: 12px; }
        .notice { margin: 0 0 10px; padding: 7px 9px; color: #52697f; background: #f2f8fb; border: 1px solid #d8e5ee; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d8e5ee; padding: 4px; vertical-align: top; }
        th { background: #1e3a5f; color: #fff; font-size: 7.5px; text-transform: uppercase; }
        tr:nth-child(even) td { background: #f8fbfd; }
        .num { text-align: right; }
        .footer { position: fixed; left: 26px; right: 26px; bottom: 10px; color: #678096; font-size: 7px; border-top: 1px solid #d8e5ee; padding-top: 5px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>Kardex de inventario</h1>
                <div class="meta">
                    Generado: {{ $generadoEn->format('d/m/Y H:i') }}
                    @if($clienteSeleccionado)
                        | Cliente: {{ $clienteSeleccionado->nombre_mostrable }}
                    @endif
                </div>
            </td>
            <td class="badge">Movimientos de stock</td>
        </tr>
    </table>
    <div class="line"></div>
    @if($hayMasMovimientos)
        <p class="notice">Se muestran los primeros {{ $pdfLimit }} movimientos del filtro aplicado. Reduzca el período o seleccione un producto para consultar los demás movimientos.</p>
    @endif
    <table>
        <thead>
            <tr>
                <th>Fecha / hora de registro</th>
                <th>Código</th>
                <th>Producto</th>
                <th>Tipo</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Precio/Costo</th>
                <th>Total linea</th>
                <th>Stock anterior</th>
                <th>Stock nuevo</th>
                <th>Lote</th>
                <th>Referencia</th>
                <th>Tercero</th>
                <th>Observación</th>
                <th>Usuario que registró</th>
            </tr>
        </thead>
        <tbody>
            @foreach($movimientos as $m)
                <tr>
                    @php($registro = $m->fecha_movimiento ?: $m->created_at)
                    <td>{{ $registro?->format('d/m/Y') ?: '-' }}<br>{{ $registro?->format('H:i:s') ?: '-' }}</td>
                    <td>{{ $m->producto?->codigo }}</td>
                    <td>{{ $m->producto?->nombre }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $m->tipo_movimiento)) }}</td>
                    <td class="num">{{ in_array($m->tipo_movimiento, ['entrada', 'ajuste_positivo', 'devolucion', 'anulacion'], true) ? number_format($m->cantidad, 2) : '-' }}</td>
                    <td class="num">{{ in_array($m->tipo_movimiento, ['salida', 'ajuste_negativo'], true) ? number_format($m->cantidad, 2) : '-' }}</td>
                    <td class="num">{{ $m->kardex_precio_unitario !== null ? 'Bs ' . number_format((float) $m->kardex_precio_unitario, 2) : '-' }}</td>
                    <td class="num">{{ $m->kardex_total_linea !== null ? 'Bs ' . number_format((float) $m->kardex_total_linea, 2) : '-' }}</td>
                    <td class="num">{{ number_format($m->stock_anterior, 2) }}</td>
                    <td class="num">{{ number_format($m->stock_nuevo, 2) }}</td>
                    <td>{{ $m->kardex_lote }}</td>
                    <td>{{ $m->kardex_documento }}</td>
                    <td>{{ $m->kardex_tercero }}</td>
                    <td>{{ $m->kardex_observacion }}</td>
                    <td>{{ $m->usuario?->name ?: $m->usuario?->username ?: 'Sin usuario registrado (histórico)' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="footer">Reporte generado desde los movimientos registrados de inventario.</div>
</body>
</html>


