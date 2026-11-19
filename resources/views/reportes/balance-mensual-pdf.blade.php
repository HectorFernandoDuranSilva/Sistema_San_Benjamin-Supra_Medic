<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 30px 34px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1b2838; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .header td { border: 0; padding: 0; vertical-align: middle; }
        h1 { margin: 0; font-size: 21px; color: #1e3a5f; }
        h2 { margin: 16px 0 7px; font-size: 13px; color: #1e3a5f; }
        .meta { margin-top: 4px; color: #61758a; }
        .period { text-align: right; font-size: 13px; font-weight: 700; color: #2388d9; }
        .line { height: 4px; background: #67bed9; border-radius: 99px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #d8e5ee; padding: 6px; }
        th { background: #1e3a5f; color: #fff; font-size: 8.5px; text-transform: uppercase; }
        tr:nth-child(even) td { background: #f8fbfd; }
        .num { text-align: right; }
        .negative { color: #b42318; font-weight: 700; }
        .note { color: #52697f; margin: 0 0 8px; padding: 8px 10px; background: #f2f8fb; border: 1px solid #d8e5ee; border-radius: 8px; }
        .footer { position: fixed; left: 30px; right: 30px; bottom: 12px; color: #678096; font-size: 8px; border-top: 1px solid #d8e5ee; padding-top: 6px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>Balance mensual de compras y ventas</h1>
                <div class="meta">Generado: {{ $generadoEn->format('d/m/Y H:i') }} | Rango: {{ \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y') }}</div>
            </td>
            <td class="period">{{ $periodo->format('m/Y') }}</td>
        </tr>
    </table>
    <div class="line"></div>

    <table>
        <tr><th>Ventas facturadas</th><th>Compras facturadas</th><th>Gastos operativos</th><th>Diferencia</th><th>IVA 13%</th><th>IT 3%</th><th>Por pagar</th></tr>
        <tr>
            <td class="num">Bs {{ number_format($totales['ventas_facturas'], 2) }}</td>
            <td class="num">Bs {{ number_format($totales['compras_facturas'], 2) }}</td>
            <td class="num">Bs {{ number_format($totales['gastos_operativos'], 2) }}</td>
            <td class="num">Bs {{ number_format($totales['diferencia_tributaria'], 2) }}</td>
            <td class="num">Bs {{ number_format($totales['iva_estimado'], 2) }}</td>
            <td class="num">Bs {{ number_format($totales['it_estimado'], 2) }}</td>
            <td class="num {{ $totales['por_pagar'] < 0 ? 'negative' : '' }}">Bs {{ number_format($totales['por_pagar'], 2) }}</td>
        </tr>
    </table>

    <h2>Notas de entrega</h2>
    <p class="note">Total informativo de notas de entrega: <strong>Bs {{ number_format($totales['notas_entrega'], 2) }}</strong>. No se suma a ventas facturadas, compras facturadas, IVA, IT, por pagar ni resultado comercial.</p>

    <h2>Detalle por nombre comercial</h2>
    <table>
        <tr>
            <th>Nombre comercial</th>
            <th>Ventas facturas</th>
            <th>Compras facturas</th>
            <th>Gastos operativos</th>
            <th>Diferencia</th>
            <th>IVA 13%</th>
            <th>IT 3%</th>
            <th>Por pagar</th>
            <th>Resultado</th>
        </tr>
        @foreach($porNombre as $item)
            <tr>
                <td>{{ $item['nombre_comercial'] }}</td>
                <td class="num">Bs {{ number_format($item['ventas_facturas'], 2) }}</td>
                <td class="num">Bs {{ number_format($item['compras_facturas'], 2) }}</td>
                <td class="num">Bs {{ number_format($item['gastos_operativos'], 2) }}</td>
                <td class="num">Bs {{ number_format($item['diferencia_tributaria'], 2) }}</td>
                <td class="num">Bs {{ number_format($item['iva_estimado'], 2) }}</td>
                <td class="num">Bs {{ number_format($item['it_estimado'], 2) }}</td>
                <td class="num {{ $item['por_pagar'] < 0 ? 'negative' : '' }}">Bs {{ number_format($item['por_pagar'], 2) }}</td>
                <td class="num">Bs {{ number_format($item['resultado'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <th>Total</th>
            <th class="num">Bs {{ number_format($totales['ventas_facturas'], 2) }}</th>
            <th class="num">Bs {{ number_format($totales['compras_facturas'], 2) }}</th>
            <th class="num">Bs {{ number_format($totales['gastos_operativos'], 2) }}</th>
            <th class="num">Bs {{ number_format($totales['diferencia_tributaria'], 2) }}</th>
            <th class="num">Bs {{ number_format($totales['iva_estimado'], 2) }}</th>
            <th class="num">Bs {{ number_format($totales['it_estimado'], 2) }}</th>
            <th class="num {{ $totales['por_pagar'] < 0 ? 'negative' : '' }}">Bs {{ number_format($totales['por_pagar'], 2) }}</th>
            <th class="num">Bs {{ number_format($totales['resultado'], 2) }}</th>
        </tr>
    </table>

    <h2>Referencia tributaria</h2>
    <p class="note">Cálculo interno solo sobre facturas: compras menos ventas, IVA 13%, IT 3% sobre ventas y Por pagar como suma de IVA más IT. Las notas de entrega se consultan por separado en Notas/Facturas y no ingresan a este balance.</p>
    <table>
        <tr><th>Concepto</th><th>Total</th></tr>
        <tr><td>Diferencia (compras - ventas)</td><td class="num">Bs {{ number_format($totales['diferencia_tributaria'], 2) }}</td></tr>
        <tr><td>IVA 13%</td><td class="num">Bs {{ number_format($totales['iva_estimado'], 2) }}</td></tr>
        <tr><td>IT 3% sobre ventas</td><td class="num">Bs {{ number_format($totales['it_estimado'], 2) }}</td></tr>
        <tr><td>Por pagar</td><td class="num {{ $totales['por_pagar'] < 0 ? 'negative' : '' }}">Bs {{ number_format($totales['por_pagar'], 2) }}</td></tr>
    </table>
    <div class="footer">Reporte administrativo generado desde compras y ventas registradas.</div>
</body>
</html>
