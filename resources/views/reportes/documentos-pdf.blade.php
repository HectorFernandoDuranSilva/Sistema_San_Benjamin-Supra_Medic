<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 30px 34px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1b2838; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .header td { border: 0; padding: 0; vertical-align: middle; }
        h1 { margin: 0; font-size: 21px; color: #1e3a5f; }
        h2 { margin: 16px 0 7px; font-size: 13px; color: #1e3a5f; }
        .meta { margin-top: 4px; color: #61758a; }
        .badge { text-align: right; font-size: 13px; font-weight: 700; color: #2388d9; }
        .line { height: 4px; background: #67bed9; border-radius: 99px; margin-bottom: 14px; }
        .summary { width: 100%; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d8e5ee; padding: 5px; }
        th { background: #1e3a5f; color: #fff; font-size: 8px; text-transform: uppercase; }
        tr:nth-child(even) td { background: #f8fbfd; }
        .num { text-align: right; }
        .negative { color: #b42318; font-weight: 700; }
        .footer { position: fixed; left: 30px; right: 30px; bottom: 12px; color: #678096; font-size: 8px; border-top: 1px solid #d8e5ee; padding-top: 6px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>Reporte de notas y facturas</h1>
                <div class="meta">Generado: {{ $generadoEn->format('d/m/Y H:i') }} | Período: {{ \Illuminate\Support\Carbon::parse($filtros['desde'])->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($filtros['hasta'])->format('d/m/Y') }}</div>
            </td>
            <td class="badge">Documentos separados</td>
        </tr>
    </table>
    <div class="line"></div>

    <table class="summary">
        <tr><th>Ventas facturadas</th><th>Compras facturadas</th><th>IVA 13%</th><th>IT 3%</th><th>Por pagar</th></tr>
        <tr>
            <td class="num">Bs {{ number_format($totales['ventas_facturadas'], 2) }}</td>
            <td class="num">Bs {{ number_format($totales['compras_facturadas'], 2) }}</td>
            <td class="num">Bs {{ number_format($totales['iva_estimado'], 2) }}</td>
            <td class="num">Bs {{ number_format($totales['it_estimado'], 2) }}</td>
            <td class="num {{ $totales['por_pagar'] < 0 ? 'negative' : '' }}">Bs {{ number_format($totales['por_pagar'], 2) }}</td>
        </tr>
    </table>

    <h2>Resumen de ventas</h2>
    <table>
        <tr><th>Nombre comercial</th><th>Documento</th><th>Cantidad</th><th>Total</th></tr>
        @forelse($ventasResumen as $item)
            <tr><td>{{ $item['nombre_comercial'] }}</td><td>{{ $item['tipo_documento'] === 'nota_entrega' ? 'Nota de entrega' : 'Factura' }}</td><td class="num">{{ $item['cantidad'] }}</td><td class="num">Bs {{ number_format($item['total'], 2) }}</td></tr>
        @empty
            <tr><td colspan="4">Sin ventas en el rango seleccionado.</td></tr>
        @endforelse
    </table>

    <h2>Resumen de compras</h2>
    <table>
        <tr><th>Nombre comercial</th><th>Documento</th><th>Cantidad</th><th>Total</th></tr>
        @forelse($comprasResumen as $item)
            <tr><td>{{ $item['nombre_comercial'] }}</td><td>{{ $item['tipo_documento'] === 'nota_entrega' ? 'Nota de entrega' : 'Factura' }}</td><td class="num">{{ $item['cantidad'] }}</td><td class="num">Bs {{ number_format($item['total'], 2) }}</td></tr>
        @empty
            <tr><td colspan="4">Sin compras en el rango seleccionado.</td></tr>
        @endforelse
    </table>

    <h2>Notas de venta</h2>
    <table>
        <tr><th>Fecha</th><th>No.</th><th>Nombre comercial</th><th>NIT</th><th>Cliente</th><th>Total</th></tr>
        @forelse($notas as $nota)
            <tr><td>{{ $nota->fecha->format('d/m/Y') }}</td><td>{{ $nota->numero_venta }}</td><td>{{ $nota->nombre_comercial }}</td><td>{{ $nota->nit_cliente }}</td><td>{{ $nota->cliente?->nombre_mostrable ?? 'Sin cliente' }}</td><td class="num">Bs {{ number_format($nota->total, 2) }}</td></tr>
        @empty
            <tr><td colspan="6">Sin notas de venta registradas.</td></tr>
        @endforelse
    </table>

    <h2>Facturas de venta</h2>
    <table>
        <tr><th>Fecha</th><th>No.</th><th>Documento</th><th>Nombre comercial</th><th>NIT</th><th>Cliente</th><th>Total</th></tr>
        @forelse($ventas as $venta)
            <tr><td>{{ $venta->fecha->format('d/m/Y') }}</td><td>{{ $venta->numero_venta }}</td><td>{{ $venta->tipo_documento === 'nota_entrega' ? 'Nota de entrega' : 'Factura' }}</td><td>{{ $venta->nombre_comercial }}</td><td>{{ $venta->nit_cliente }}</td><td>{{ $venta->cliente?->nombre_mostrable ?? 'Sin cliente' }}</td><td class="num">Bs {{ number_format($venta->total, 2) }}</td></tr>
        @empty
            <tr><td colspan="7">Sin ventas registradas.</td></tr>
        @endforelse
    </table>

    <h2>Facturas de compra</h2>
    <table>
        <tr><th>Fecha</th><th>No.</th><th>Documento</th><th>Nombre comercial</th><th>NIT</th><th>Proveedor</th><th>Total</th></tr>
        @forelse($compras as $compra)
            <tr><td>{{ $compra->fecha->format('d/m/Y') }}</td><td>{{ $compra->numero_compra }}</td><td>Factura</td><td>{{ $compra->nombre_comercial }}</td><td>{{ $compra->nit_proveedor }}</td><td>{{ $compra->proveedor?->nombre ?? 'Sin proveedor' }}</td><td class="num">Bs {{ number_format($compra->total, 2) }}</td></tr>
        @empty
            <tr><td colspan="7">Sin facturas de compra registradas.</td></tr>
        @endforelse
    </table>

    <h2>Notas de compra</h2>
    <table>
        <tr><th>Fecha</th><th>No.</th><th>Nombre comercial</th><th>NIT</th><th>Proveedor</th><th>Total</th></tr>
        @forelse($notasCompras as $compra)
            <tr><td>{{ $compra->fecha->format('d/m/Y') }}</td><td>{{ $compra->numero_compra }}</td><td>{{ $compra->nombre_comercial }}</td><td>{{ $compra->nit_proveedor }}</td><td>{{ $compra->proveedor?->nombre ?? 'Sin proveedor' }}</td><td class="num">Bs {{ number_format($compra->total, 2) }}</td></tr>
        @empty
            <tr><td colspan="6">Sin notas de compra registradas.</td></tr>
        @endforelse
    </table>

    <h2>Referencia tributaria</h2>
    <table>
        <tr><th>Empresa</th><th>Ventas</th><th>Compras</th><th>Diferencia</th><th>IVA 13%</th><th>IT 3%</th><th>Por pagar</th></tr>
        @foreach($tributarioPorNombre as $item)
            <tr>
                <td>{{ $item['nombre_comercial'] }}</td>
                <td class="num">Bs {{ number_format($item['ventas_facturadas'], 2) }}</td>
                <td class="num">Bs {{ number_format($item['compras_facturadas'], 2) }}</td>
                <td class="num">Bs {{ number_format($item['diferencia_tributaria'], 2) }}</td>
                <td class="num">Bs {{ number_format($item['iva_estimado'], 2) }}</td>
                <td class="num">Bs {{ number_format($item['it_estimado'], 2) }}</td>
                <td class="num {{ $item['por_pagar'] < 0 ? 'negative' : '' }}">Bs {{ number_format($item['por_pagar'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <th>Total</th><th class="num">Bs {{ number_format($totales['ventas_facturadas'], 2) }}</th><th class="num">Bs {{ number_format($totales['compras_facturadas'], 2) }}</th><th class="num">Bs {{ number_format($totales['diferencia_tributaria'], 2) }}</th><th class="num">Bs {{ number_format($totales['iva_estimado'], 2) }}</th><th class="num">Bs {{ number_format($totales['it_estimado'], 2) }}</th><th class="num {{ $totales['por_pagar'] < 0 ? 'negative' : '' }}">Bs {{ number_format($totales['por_pagar'], 2) }}</th>
        </tr>
    </table>
    <div class="footer">Reporte generado separando notas de entrega y facturas.</div>
</body>
</html>
