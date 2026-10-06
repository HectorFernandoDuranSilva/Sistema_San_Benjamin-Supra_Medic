<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Recibo compra {{ $compra->numero_compra }}</title>
<style>
    @page { margin: 0; size: letter portrait; }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: Arial, Helvetica, sans-serif;
        color: #001f2c;
        font-size: 10pt;
        background: #fff;
    }
    .page {
        position: relative;
        width: 612pt;
        height: 0;
        padding: 0;
        overflow: visible;
    }
    .logo-box {
        position: absolute;
        left: 30pt;
        top: 44pt;
        width: 84pt;
        height: 46pt;
        border: 1pt solid #999;
        background: #fff;
        text-align: center;
    }
    .logo-box img {
        width: 76pt;
        height: 40pt;
        object-fit: contain;
        margin-top: 3pt;
    }
    .company {
        position: absolute;
        left: 116pt;
        top: 49pt;
        width: 300pt;
        color: #000;
        font-size: 6.8pt;
        font-weight: 700;
        line-height: 1.35;
    }
    .company-name { margin-bottom: 4pt; }
    .receipt-box {
        position: absolute;
        right: 121pt;
        top: 43pt;
        width: 83pt;
        height: 39pt;
        border: 1.4pt solid #32bcea;
        border-radius: 8pt;
        text-align: center;
        color: #000;
        padding-top: 2pt;
        font-weight: 700;
    }
    .receipt-title { font-size: 10pt; line-height: 1; }
    .fact-row { margin-top: 4pt; color: #ff0000; font-size: 8.5pt; line-height: 1; }
    .fact-row span { display: inline-block; min-width: 38pt; }
    .number-row { margin-top: 5pt; font-size: 8.5pt; line-height: 1; }
    .number-row span { display: inline-block; min-width: 38pt; }
    .supplier-area {
        position: absolute;
        left: 29pt;
        top: 90pt;
        width: 268pt;
        border-collapse: collapse;
        table-layout: fixed;
    }
    .supplier-area td {
        height: 9pt;
        border: 0.7pt solid #0b2b5e;
        padding: 0 3pt;
        vertical-align: middle;
        font-size: 6.5pt;
        line-height: 1;
    }
    .supplier-area .label {
        background: #0b2b39;
        color: #fff;
        font-weight: 700;
        text-align: right;
    }
    .supplier-area .value {
        color: #000;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
    }
    .date-line {
        position: absolute;
        right: 121pt;
        top: 100pt;
        width: 83pt;
        color: #000;
        font-weight: 700;
        font-size: 7.3pt;
        white-space: nowrap;
    }
    .date-line .date {
        display: inline-block;
        width: 50pt;
        margin-left: 5pt;
        color: #003399;
        text-align: center;
        font-weight: 400;
        border-bottom: 1pt solid #e6aa00;
        padding-bottom: 1pt;
    }
    .items {
        position: absolute;
        left: 28pt;
        top: 115pt;
        width: 516pt;
        border-collapse: collapse;
        table-layout: fixed;
        color: #000;
    }
    .items th {
        height: 9pt;
        border: 0.7pt solid #000;
        background: #0b2b39;
        color: #fff;
        font-size: 7pt;
        line-height: 1;
        text-align: center;
        font-weight: 700;
        padding: 1pt 2pt;
    }
    .items td {
        height: 9pt;
        border: 0.7pt solid #000;
        padding: 0 2pt;
        font-size: 6.7pt;
        line-height: 1;
        vertical-align: middle;
        white-space: nowrap;
        overflow: hidden;
    }
    .items .center { text-align: center; }
    .items .right { text-align: right; }
    .items .bold { font-weight: 700; }
    .money-currency {
        display: inline-block;
        width: 13pt;
        text-align: left;
    }
    .money-value {
        display: inline-block;
        width: 31pt;
        text-align: right;
    }
    .signatures {
        position: absolute;
        left: 52pt;
        top: 241pt;
        width: 245pt;
        color: #0b2b39;
        text-align: center;
        font-size: 7pt;
        font-weight: 700;
        white-space: nowrap;
    }
    .signature-line {
        display: inline-block;
        width: 88pt;
        margin: 0 13pt 3pt;
        border-top: 1pt dotted #000;
        height: 7pt;
    }
    .signature-label {
        display: inline-block;
        width: 88pt;
        margin: 0 13pt;
    }
    .bottom-rule {
        position: absolute;
        left: 29pt;
        top: 263pt;
        width: 516pt;
        border-top: 0.7pt solid #0b2b5e;
    }
</style>
</head>
<body>
@php
    $detalles = $compra->detalles->values();
    $brandName = str_contains(strtolower((string) $compra->nombre_comercial), 'supra') ? 'Supra Medic' : 'San Benjamin';
    $brandLogoPath = $brandName === 'Supra Medic'
        ? public_path('assets/logos/supra-medic.jpeg')
        : public_path('assets/logos/san-benjamin.jpeg');
    $brandLogoAsset = $brandName === 'Supra Medic'
        ? asset('assets/logos/supra-medic.jpeg')
        : asset('assets/logos/san-benjamin.jpeg');
    $brandLogo = ($isPdf ?? false) ? $brandLogoPath : $brandLogoAsset;
    $companyName = $brandName === 'Supra Medic' ? 'SUPRA MEDIC IMPORTACIONES' : 'SAN BENJAMIN S.R.L';
    $telefono = $empresa->telefono ?: '6442910 - 70328125 - 67632137';
    $direccion = $empresa->direccion ?: 'CALLE GRAU N° 661';
    $barrio = 'ZONA/BARRIO SANTA ANA - SUCRE';
    $money = fn ($value) => number_format((float) $value, 2, '.', ',');
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    $comment = $compra->observacion ?: 'COMPRAS';
@endphp
<div class="page">
    <div class="logo-box"><img src="{{ $brandLogo }}" alt="{{ $brandName }}"></div>
    <div class="company">
        <div class="company-name">{{ $companyName }}</div>
        <div>TELEFONOS:&nbsp; {{ $telefono }}</div>
        <div>{{ $direccion }}</div>
        <div>{{ $barrio }}</div>
    </div>

    <div class="receipt-box">
        <div class="receipt-title">INGRESO</div>
        <div class="fact-row">Fact. <span>{{ $compra->factura ?: $compra->numero_compra }}</span></div>
        <div class="number-row">N° <span>{{ $compra->id }}</span></div>
    </div>

    <table class="supplier-area">
        <colgroup>
            <col style="width: 49pt;">
            <col style="width: 219pt;">
        </colgroup>
        <tr>
            <td class="label">Proveedor:</td>
            <td class="value">{{ $compra->proveedor?->nombre ?? 'SIN PROVEEDOR' }}</td>
        </tr>
        <tr>
            <td class="label">Comentario:</td>
            <td class="value">{{ $comment }}</td>
        </tr>
    </table>
    <div class="date-line">Fecha: <span class="date">{{ $compra->fecha->format('d/m/Y') }}</span></div>

    <table class="items">
        <colgroup>
            <col style="width: 49pt;">
            <col style="width: 151pt;">
            <col style="width: 31pt;">
            <col style="width: 38pt;">
            <col style="width: 50pt;">
            <col style="width: 42pt;">
            <col style="width: 60pt;">
            <col style="width: 41pt;">
            <col style="width: 54pt;">
        </colgroup>
        <tr>
            <th>CODIGO</th>
            <th>DESCRIPCION</th>
            <th>MEDIDA</th>
            <th>CANTIDAD</th>
            <th>PRECIO</th>
            <th>DESC</th>
            <th>SUBTOTAL</th>
            <th>IMPUESTO</th>
            <th>TOTAL</th>
        </tr>
        @foreach($detalles as $detalle)
            @php
                $detalleImpuesto = (float) ($detalle->impuesto ?? 0);
                $detalleTotal = (float) ($detalle->total ?? 0);
                $detalleTotal = $detalleTotal > 0 ? $detalleTotal : ((float) $detalle->subtotal + $detalleImpuesto);
            @endphp
            <tr>
                <td class="center">{{ $detalle->codigo_producto }}</td>
                <td>{{ $detalle->nombre_producto }}</td>
                <td class="center">{{ $detalle->producto?->medida ?: 'PIEZAS' }}</td>
                <td class="center">{{ $qty($detalle->cantidad) }}</td>
                <td class="right"><span class="money-currency">Bs</span><span class="money-value">{{ $money($detalle->costo_unitario) }}</span></td>
                <td class="right">{{ number_format((float) $detalle->porcentaje_utilidad, 2) }}%</td>
                <td class="right"><span class="money-currency">Bs</span><span class="money-value">{{ $money($detalle->subtotal) }}</span></td>
                <td class="right"><span class="money-currency">Bs</span><span class="money-value">{{ $detalleImpuesto > 0 ? $money($detalleImpuesto) : '-' }}</span></td>
                <td class="right"><span class="money-currency">Bs</span><span class="money-value">{{ $money($detalleTotal) }}</span></td>
            </tr>
        @endforeach
        <tr>
            <td></td>
            <td class="bold center">Total</td>
            <td colspan="3"></td>
            <td class="right bold"><span class="money-currency">Bs</span><span class="money-value">-</span></td>
            <td class="right bold"><span class="money-currency">Bs</span><span class="money-value">{{ $money($compra->subtotal) }}</span></td>
            <td class="right bold"><span class="money-currency">Bs</span><span class="money-value">{{ (float) ($compra->impuesto_total ?? 0) > 0 ? $money($compra->impuesto_total) : '-' }}</span></td>
            <td class="right bold"><span class="money-currency">Bs</span><span class="money-value">{{ $money($compra->total) }}</span></td>
        </tr>
    </table>

    <div class="signatures">
        <span class="signature-line"></span><span class="signature-line"></span>
        <span class="signature-label">Firma</span><span class="signature-label">Nombre</span>
    </div>
    <div class="bottom-rule"></div>
</div>
</body>
</html>
