<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Recibo venta prueba lotes {{ $venta->numero_venta }}</title>
@php
    $detalles = $venta->detalles->values();
    $cliente = $venta->cliente;
    $nit = $venta->nit_cliente ?: ($cliente?->documento ?? '');
    $brandName = str_contains(strtolower((string) $venta->nombre_comercial), 'supra') ? 'Supra Medic' : 'San Benjamin';
    $brandColor = $brandName === 'Supra Medic' ? '#31d1c9' : '#86bc25';
    $brandSoftColor = $brandName === 'Supra Medic' ? '#e7fbfa' : '#eaf3dc';
    $brandTitleColor = $brandName === 'Supra Medic' ? '#087f92' : '#0b2b39';
    $brandLogoPath = $brandName === 'Supra Medic'
        ? public_path('assets/logos/supra-medic.jpeg')
        : public_path('assets/logos/san-benjamin.jpeg');
    $brandLogoAsset = $brandName === 'Supra Medic'
        ? asset('assets/logos/supra-medic.jpeg')
        : asset('assets/logos/san-benjamin.jpeg');
    $brandLogo = ($isPdf ?? false) ? $brandLogoPath : $brandLogoAsset;
    $orden = $brandName;
    $factura = $venta->factura ?: 'S/F';
    $diasPago = 10;
    $fechaLimite = $venta->fecha?->copy()->addDays($diasPago);
    $money = fn ($value) => number_format((float) $value, 2, '.', ',');
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    $lotes = $venta->detalles
        ->flatMap(fn ($detalle) => $detalle->lotes->map(fn ($consumo) => [
            'codigo_producto' => $detalle->codigo_producto,
            'producto' => $detalle->nombre_producto,
            'lote' => $consumo->lote?->codigo_lote ?: 'sin lote',
            'vencimiento' => $consumo->fecha_vencimiento,
            'cantidad' => $consumo->cantidad,
        ]))
        ->values();
@endphp
<style>
    @page { margin: 0.43in 0.28in 0.28in 0.28in; size: letter portrait; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Tahoma, Arial, sans-serif; color: #000; font-size: 10px; }
    .toolbar { margin: 0 0 12px; }
    .receipt-wrap { position: relative; }
    .brand-logo { position: absolute; top: 0.05in; left: 0.02in; width: 1.18in; max-height: 0.78in; object-fit: contain; }
    .excel-sheet { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .excel-sheet td, .excel-sheet th { padding: 2px 5px; vertical-align: middle; }
    .border { border: 1px solid #000; }
    .right { text-align: right; }
    .center { text-align: center; }
    .left { text-align: left; }
    .bold { font-weight: 700; }
    .title { font-size: 15px; color: {{ $brandTitleColor }}; font-weight: 700; text-align: center; }
    .label { font-size: 10px; font-weight: 700; text-align: right; }
    .value { font-size: 10px; font-weight: 700; }
    .small-label { font-size: 8px; font-weight: 700; text-align: right; }
    .green { background: {{ $brandColor }}; }
    .detail-header th,
    .batch-header th { background: {{ $brandColor }}; border: 1px solid #000; font-size: 9px; font-weight: 700; text-align: center; height: 13px; }
    .detail td { border-left: 1px solid #000; border-right: 1px solid #000; height: 11px; font-size: 8px; line-height: 1.0; white-space: nowrap; overflow: hidden; }
    .detail-start td { border-top: 1px solid #000; }
    .detail-end td { border-bottom: 1px solid #000; }
    .batch-title { background: {{ $brandSoftColor }}; border-left: 1px solid #000; border-right: 1px solid #000; font-size: 9px; font-weight: 700; text-align: center; color: {{ $brandTitleColor }}; }
    .batch-cell { padding: 0 !important; border-left: 1px solid #000; border-right: 1px solid #000; border-bottom: 1px solid #000; }
    .batch-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .batch-table th { background: {{ $brandColor }}; border: 1px solid #000; font-size: 9px; font-weight: 700; text-align: center; height: 13px; padding: 2px 4px; }
    .batch-table td { border-left: 1px solid #000; border-right: 1px solid #000; height: 12px; font-size: 7.5px; line-height: 1.05; padding: 2px 4px; white-space: nowrap; overflow: hidden; }
    .summary td { border: 1px solid #000; height: 11px; font-size: 9px; }
    .comment-label { background: {{ $brandColor }}; border: 1px solid #000; font-size: 9px; font-weight: 700; text-align: center; }
    .comment-box { border: 1px solid #000; font-size: 9px; font-weight: 700; text-align: center; vertical-align: middle; }
    .terms td { font-size: 7.5px; line-height: 1.0; }
    .signature-cell { padding-top: 14px !important; }
    .signature-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .signature-table td { border: 0; text-align: center; padding: 0 28px; }
    .signature-line { width: 1.55in; margin: 0 auto 5px; border-top: 1px dotted #000; height: 1px; }
    .signature-label { font-size: 9px; font-weight: 700; color: {{ $brandTitleColor }}; }
    .spacer-1 td { height: 4px; font-size: 1px; line-height: 1px; }
    .spacer-2 td { height: 7px; }
    @media print { .toolbar { display: none; } }
</style>
</head>
<body>
@unless($isPdf ?? false)
<div class="toolbar"><button onclick="window.print()">Imprimir</button></div>
@endunless
<div class="receipt-wrap">
<img class="brand-logo" src="{{ $brandLogo }}" alt="{{ $brandName }}">
<table class="excel-sheet">
    <colgroup>
        <col style="width:10%;">
        <col style="width:47%;">
        <col style="width:11%;">
        <col style="width:7%;">
        <col style="width:10%;">
        <col style="width:15%;">
    </colgroup>
    <tr class="spacer-1"><td colspan="6"></td></tr>
    <tr><td colspan="6" class="title">Nota de Entrega</td></tr>
    <tr class="spacer-1"><td colspan="6"></td></tr>
    <tr>
        <td colspan="4"></td>
        <td class="label border">Fecha&nbsp;&nbsp;:</td>
        <td class="value center border">{{ $venta->fecha->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td colspan="4"></td>
        <td class="label border">Factura&nbsp;&nbsp;:</td>
        <td class="value center border">{{ $factura }}</td>
    </tr>
    <tr>
        <td colspan="4"></td>
        <td class="label border">NIT&nbsp;&nbsp;:</td>
        <td class="value center border">{{ $nit }}</td>
    </tr>
    <tr>
        <td class="small-label"></td>
        <td colspan="3"></td>
        <td class="label border">Orden&nbsp;&nbsp;:</td>
        <td class="value center border">{{ $orden }}</td>
    </tr>
    <tr>
        <td class="small-label">Dirección:</td>
        <td colspan="3" class="left">{{ $empresa->direccion ?: ' CALLE GRAU No 661' }}</td>
        <td class="label border">Número&nbsp;&nbsp;:</td>
        <td class="value center border">{{ $venta->numero_venta }}</td>
    </tr>
    <tr class="spacer-2"><td colspan="6"></td></tr>
    <tr>
        <td class="green bold center">CLIENTE</td>
        <td colspan="5" class="green"></td>
    </tr>
    <tr>
        <td class="label">Cliente :</td>
        <td colspan="5" class="value left">{{ $cliente?->nombre ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">Compañía :</td>
        <td colspan="5" class="value left">{{ $cliente?->nombre ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">NIT :</td>
        <td colspan="5" class="value left">{{ $nit }}</td>
    </tr>
    <tr>
        <td class="label">Contacto :</td>
        <td colspan="5" class="value left"></td>
    </tr>
    <tr>
        <td class="label">Teléfono :</td>
        <td colspan="5" class="value left">{{ $cliente?->telefono ?? '' }}</td>
    </tr>
    <tr class="spacer-2"><td colspan="6"></td></tr>
    <tr class="detail-header">
        <th>CÓDIGO</th>
        <th>DESCRIPCIÓN</th>
        <th>MEDIDA</th>
        <th>CANT</th>
        <th>PRECIO</th>
        <th>TOTAL</th>
    </tr>
    @for($i = 0; $i < 18; $i++)
        @php $d = $detalles[$i] ?? null; @endphp
        <tr class="detail {{ $i === 0 ? 'detail-start' : '' }} {{ $i === 17 && $lotes->isEmpty() ? 'detail-end' : '' }}">
            <td class="center">{{ $d?->codigo_producto }}</td>
            <td class="left">{{ $d?->nombre_producto }}</td>
            <td class="center">{{ $d?->producto?->medida }}</td>
            <td class="center">{{ $d ? $qty($d->cantidad) : '' }}</td>
            <td class="right">{{ $d ? $money($d->precio_unitario) : '' }}</td>
            <td class="right">{{ $d ? $money($d->total ?: $d->subtotal) : '' }}</td>
        </tr>
    @endfor
    @if($lotes->isNotEmpty())
        <tr><td colspan="6" class="batch-title">LOTES Y FECHAS DE VENCIMIENTO</td></tr>
        <tr>
            <td colspan="6" class="batch-cell">
                <table class="batch-table">
                    <colgroup>
                        <col style="width: 10%;">
                        <col style="width: 45%;">
                        <col style="width: 22%;">
                        <col style="width: 13%;">
                        <col style="width: 10%;">
                    </colgroup>
                    <tr>
                        <th>CÓDIGO</th>
                        <th>PRODUCTO</th>
                        <th>LOTE</th>
                        <th>VENC.</th>
                        <th>CANT.</th>
                    </tr>
                    @for($i = 0; $i < 9; $i++)
                        @php $lote = $lotes[$i] ?? null; @endphp
                        <tr>
                            <td class="center">{{ $lote['codigo_producto'] ?? '' }}</td>
                            <td class="left">{{ $lote['producto'] ?? '' }}</td>
                            <td class="center">{{ $lote['lote'] ?? '' }}</td>
                            <td class="center">{{ $lote ? ($lote['vencimiento']?->format('d/m/Y') ?: 'Sin fecha') : '' }}</td>
                            <td class="center">{{ isset($lote['cantidad']) ? $qty($lote['cantidad']) : '' }}</td>
                        </tr>
                    @endfor
                </table>
            </td>
        </tr>
    @endif
    <tr class="summary">
        <td colspan="2" class="comment-label">Comentario</td>
        <td colspan="2"></td>
        <td class="bold right">Suma</td>
        <td class="right">{{ $money($venta->subtotal) }}</td>
    </tr>
    <tr class="summary">
        <td colspan="2" rowspan="4" class="comment-box">{{ $venta->observacion ?: 'VENTAS' }}</td>
        <td colspan="2"></td>
        <td class="bold right">Descuento</td>
        <td class="right">{{ $money($venta->descuento) }}</td>
    </tr>
    <tr class="summary">
        <td colspan="2"></td>
        <td class="bold right">Subtotal</td>
        <td class="right">{{ $money(max(0, (float) $venta->subtotal - (float) $venta->descuento)) }}</td>
    </tr>
    <tr class="summary">
        <td colspan="2"></td>
        <td class="bold right">IVA</td>
        <td class="right">{{ $money($venta->impuesto_total) }}</td>
    </tr>
    <tr class="summary">
        <td colspan="2"></td>
        <td class="bold right">Total</td>
        <td class="right">{{ $money($venta->total) }}</td>
    </tr>
    <tr class="spacer-1"><td colspan="6"></td></tr>
    <tr class="terms"><td colspan="6">EL MATERIAL ENTREGADO SE ENCUENTRA CONFORME EN CANTIDAD Y ESTADO, CUALQUIER OBSERVACIÓN O RECLAMO DEBERÁ</td></tr>
    <tr class="terms"><td colspan="4">REALIZARSE EN UN PLAZO MÁXIMO DE 48 HORAS, EL PAGO DEBERÁ EFECTUARSE DENTRO DE LOS</td><td class="center">{{ $diasPago }}</td><td>DÍAS CALENDARIOS</td></tr>
    <tr class="terms"><td colspan="6">POSTERIORES A LA FECHA DE EMISIÓN DE LA FACTURA CORRESPONDIENTE:</td></tr>
    <tr class="terms"><td></td><td colspan="3" class="center">FECHA LÍMITE DE CANCELACIÓN -</td><td colspan="2">{{ $fechaLimite?->format('d/m/Y') }}</td></tr>
    <tr class="spacer-2"><td colspan="6"></td></tr>
    <tr>
        <td colspan="6" class="signature-cell">
            <table class="signature-table">
                <tr>
                    <td>
                        <div class="signature-line"></div>
                        <div class="signature-label">Firma</div>
                    </td>
                    <td>
                        <div class="signature-line"></div>
                        <div class="signature-label">Nombre</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</div>
</body>
</html>

