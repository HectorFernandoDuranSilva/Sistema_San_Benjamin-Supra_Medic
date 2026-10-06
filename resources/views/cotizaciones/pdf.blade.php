<!doctype html>
<html lang="es">
<head><meta charset="utf-8">
<style>
@page { margin: 38pt 40pt; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; margin: 0; }
table { width: 100%; border-collapse: collapse; }
.header td { vertical-align: top; }
.company { font-size: 9pt; font-weight: bold; color: {{ $isSupra ? '#187caa' : '#090660' }}; line-height: 1.35; }
.logo { width: {{ $logoWidth }}pt; height: {{ $logoHeight }}pt; }
.right { text-align: right; }
.date { border-top: 1pt solid {{ $isSupra ? '#187caa' : '#090660' }}; padding-top: 5pt; margin-top: 10pt; font-size: 8pt; }
.recipient { margin-top: 18pt; line-height: 1.4; }
h1 { font-size: 11pt; text-align: center; text-decoration: underline; margin: 16pt 0 5pt; }
.reference { text-align: center; font-size: 8pt; margin-bottom: 20pt; }
.watermark { position: fixed; top: 275pt; left: {{ $isSupra ? 116 : 60 }}pt; width: {{ $isSupra ? 300 : 420 }}pt; opacity: .035; z-index: -1; }
.detail { table-layout: fixed; font-size: 8pt; }
.detail th, .detail td { border: .5pt solid #666; padding: 5pt 3pt; vertical-align: top; overflow-wrap: break-word; }
.detail th { font-size: 6pt; text-align: left; font-weight: bold; white-space: nowrap; }
.detail thead { display: table-header-group; }
.detail tr { page-break-inside: avoid; }
.number { text-align: right; white-space: nowrap; }
.center { text-align: center; white-space: nowrap; }
.closing { page-break-inside: avoid; }
.totals td { padding: 6pt 4pt; border-bottom: .5pt solid #666; }
.terms { margin-top: 25pt; font-size: 9pt; line-height: 1.45; page-break-inside: avoid; }
.terms table { width: auto; border-collapse: collapse; }
.terms td { padding: 0 10pt 1pt 0; vertical-align: top; }
.terms .term-label { min-width: 74pt; font-weight: bold; }
.comment { white-space: pre-line; overflow-wrap: break-word; margin-top: 12pt; }
.signature { text-align: right; margin-top: 10pt; }
.signature img { width: 112pt; height: auto; }
</style></head>
<body>
<img class="watermark" src="{{ $logo }}" alt="">
<table class="header"><tr>
<td class="company">{{ $header['business'] }}<br>{{ $header['name'] }}<br>
{{ $header['address'] }}<br>
TEL.: {{ $header['phone'] }}<br>
{{ $header['city'] }}</td>
<td class="right"><img class="logo" src="{{ $logo }}" alt="{{ $profile['name'] }}"></td>
</tr></table>
<div class="date">Sucre, {{ $cotizacion->fecha->locale('es')->translatedFormat('d \d\e F \d\e Y') }}</div>
<div class="recipient"><strong>SEÑORES:<br>{{ $cotizacion->cliente?->nombre_mostrable ?? 'Sin cliente' }}</strong>
@if($cotizacion->nit_cliente)<br>NIT: {{ $cotizacion->nit_cliente }}@endif
<br><strong><u>PRESENTE.-</u></strong></div>
<h1>COTIZACIÓN</h1>
<div class="reference">N.º {{ $cotizacion->numero_cotizacion }}</div>
<table class="detail">
<thead><tr><th style="width:5%">N.º</th><th style="width:21%">DETALLE</th><th style="width:13%">PRESENTACIÓN</th><th style="width:6%">CANT.</th><th style="width:8%">MARCA</th><th style="width:12%">PROCEDENCIA</th><th style="width:11%">VENCIMIENTO</th><th style="width:11%">PRECIO<br>UNITARIO</th><th style="width:13%">PRECIO<br>TOTAL</th></tr></thead>
<tbody>@foreach($cotizacion->detalles as $detalle)
<tr><td class="center">{{ $loop->iteration }}</td><td>{{ $detalle->nombre_producto }}</td>
<td>{{ $detalle->medida ?? $detalle->producto?->medida ?: '-' }}</td>
<td class="number">{{ rtrim(rtrim(number_format($detalle->cantidad, 2, '.', ''), '0'), '.') }}</td>
<td>{{ $detalle->marca ?: '-' }}</td><td>{{ $detalle->procedencia ?: '-' }}</td><td>{{ $detalle->fecha_vencimiento?->format('d/m/Y') ?: '-' }}</td>
<td class="number">{{ number_format($detalle->precio_unitario, 2) }}</td>
<td class="number">{{ number_format($detalle->total, 2) }}</td></tr>
@endforeach</tbody></table>
<div class="closing"><table class="totals">
@if((float) $cotizacion->descuento > 0)<tr><td>Descuento aplicado (incluido en los importes)</td><td class="number">Bs {{ number_format($cotizacion->descuento, 2) }}</td></tr>@endif
<tr><td><strong>TOTAL (Bs)</strong></td><td class="number"><strong>{{ number_format($cotizacion->total, 2) }}</strong></td></tr></table>
<div class="terms">
<table>
<tr><td class="term-label">Empresa:</td><td>{{ $footer['empresa'] }}</td></tr>
<tr><td class="term-label">Dirección:</td><td>{{ $footer['direccion'] }}</td></tr>
<tr><td class="term-label">Tiempo de validez de la oferta:</td><td>{{ $footer['validez'] }}</td></tr>
<tr><td class="term-label">Tiempo de entrega:</td><td>{{ $footer['entrega'] }}</td></tr>
<tr><td class="term-label">Condiciones de pago:</td><td>{{ $footer['condiciones_pago'] }}</td></tr>
<tr><td class="term-label">Depósito a cuenta:</td><td>{{ $footer['deposito_cuenta'] }}</td></tr>
</table>
</div>
@if($cotizacion->observacion)<div class="comment">{{ $cotizacion->observacion }}</div>@endif
<div class="signature"><img src="{{ $signature }}" alt="Firma y sello de {{ $profile['name'] }}"></div>
</div></body></html>
