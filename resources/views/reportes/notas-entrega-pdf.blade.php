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
        .meta { margin-top: 4px; color: #61758a; }
        .total { text-align: right; font-size: 14px; font-weight: 700; color: #2388d9; }
        .line { height: 4px; background: #67bed9; border-radius: 99px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d8e5ee; padding: 6px; }
        th { background: #1e3a5f; color: #fff; font-size: 8px; text-transform: uppercase; }
        tr:nth-child(even) td { background: #f8fbfd; }
        .num { text-align: right; }
        .notice { margin: 0 0 12px; padding: 8px 10px; background: #f2f8fb; border: 1px solid #d8e5ee; border-radius: 8px; color: #52697f; }
        .footer { position: fixed; left: 30px; right: 30px; bottom: 12px; color: #678096; font-size: 8px; border-top: 1px solid #d8e5ee; padding-top: 6px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>Notas de entrega</h1>
                <div class="meta">Generado: {{ $generadoEn->format('d/m/Y H:i') }} | Período: {{ \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y') }}@if($nombreComercial) | Empresa: {{ $nombreComercial }}@endif</div>
            </td>
            <td class="total">Total: Bs {{ number_format($totalNotas, 2) }}</td>
        </tr>
    </table>
    <div class="line"></div>
    <p class="notice">Este reporte contiene solo notas de entrega registradas. Su total es informativo y no participa en IVA, IT, por pagar ni balance mensual.</p>

    <table>
        <tr><th>Fecha</th><th>No.</th><th>Empresa</th><th>NIT</th><th>Cliente</th><th>Total</th></tr>
        @forelse($notas as $nota)
            <tr>
                <td>{{ $nota->fecha->format('d/m/Y') }}</td>
                <td>{{ $nota->numero_venta }}</td>
                <td>{{ $nota->nombre_comercial }}</td>
                <td>{{ $nota->nit_cliente ?: '-' }}</td>
                <td>{{ $nota->cliente?->nombre_mostrable ?? 'Sin cliente' }}</td>
                <td class="num">Bs {{ number_format($nota->total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Sin notas de entrega registradas en el período seleccionado.</td></tr>
        @endforelse
        <tr><th colspan="5">Total de notas de entrega</th><th class="num">Bs {{ number_format($totalNotas, 2) }}</th></tr>
    </table>
    <div class="footer">Reporte administrativo de notas de entrega. No constituye cálculo tributario.</div>
</body>
</html>
