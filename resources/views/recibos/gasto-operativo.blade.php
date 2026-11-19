<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Comprobante de gasto operativo</title>
@php
    $brandName = str_contains(strtolower((string) $gasto->nombre_comercial), 'supra') ? 'Supra Medic' : 'San Benjamin';
    $brandColor = $brandName === 'Supra Medic' ? '#31d1c9' : '#86bc25';
    $brandSoftColor = $brandName === 'Supra Medic' ? '#e7fbfa' : '#eaf3dc';
    $brandTitleColor = $brandName === 'Supra Medic' ? '#087f92' : '#0b2b39';
    $logo = $brandName === 'Supra Medic' ? public_path('assets/logos/supra-medic.jpeg') : public_path('assets/logos/san-benjamin.jpeg');
    $money = fn ($value) => number_format((float) $value, 2, '.', ',');
    $label = fn ($value) => ucfirst(str_replace('_', ' ', (string) $value));
@endphp
<style>
    @page { margin: 0.47in 0.35in 0.4in; size: letter portrait; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Tahoma, Arial, sans-serif; color: #000; font-size: 10px; }
    .wrap { position: relative; }
    .logo { position: absolute; top: 0.08in; left: 0.02in; width: 1.18in; max-height: 0.78in; object-fit: contain; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    td, th { padding: 5px 7px; vertical-align: top; }
    .title { color: {{ $brandTitleColor }}; font-size: 17px; font-weight: 700; text-align: center; padding: 12px 0 18px; }
    .label { width: 18%; font-weight: 700; text-align: right; }
    .border { border: 1px solid #000; }
    .band { background: {{ $brandColor }}; color: #000; border: 1px solid #000; font-weight: 700; }
    .soft { background: {{ $brandSoftColor }}; color: {{ $brandTitleColor }}; border: 1px solid #000; font-weight: 700; }
    .value { font-weight: 700; }
    .amount { font-size: 16px; font-weight: 700; text-align: right; }
    .note { font-size: 9px; line-height: 1.35; }
    .signatures { margin-top: 75px; }
    .signatures td { text-align: center; padding: 0 35px; }
    .line { border-top: 1px dotted #000; height: 1px; margin-bottom: 5px; }
    .footer { margin-top: 24px; font-size: 8px; text-align: center; color: #555; }
</style>
</head>
<body>
<div class="wrap">
    <img class="logo" src="{{ $logo }}" alt="{{ $brandName }}">
    <table>
        <tr><td colspan="4" class="title">Comprobante de gasto operativo</td></tr>
        <tr>
            <td class="label">Empresa:</td><td class="value">{{ $gasto->nombre_comercial }}</td>
            <td class="label border">Fecha:</td><td class="value border">{{ $gasto->fecha?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Tipo:</td><td class="value">{{ $label($gasto->tipo) }}</td>
            <td class="label border">Número:</td><td class="value border">{{ $gasto->documento_numero ?: 'Sin número' }}</td>
        </tr>
        <tr>
            <td class="label">Operación:</td><td class="value">{{ $label($gasto->modalidad) }}</td>
            <td class="label border">Respaldo:</td><td class="value border">{{ $label($gasto->documento_tipo) }}</td>
        </tr>
        <tr><td colspan="4" style="height:16px;"></td></tr>
        <tr><td colspan="4" class="band">DETALLE DEL GASTO</td></tr>
        <tr><td class="soft">Descripción</td><td colspan="3" class="border">{{ $gasto->descripcion ?: 'Sin descripción' }}</td></tr>
        <tr><td class="soft">Observación</td><td colspan="3" class="border">{{ $gasto->observacion ?: 'Sin observación' }}</td></tr>
        <tr><td class="soft">Registró</td><td colspan="2" class="border">{{ $gasto->usuario?->name ?? 'Sin usuario' }}</td><td class="amount border">Bs {{ $money($gasto->importe) }}</td></tr>
        <tr><td colspan="4" style="height:22px;"></td></tr>
        <tr><td colspan="4" class="note">Este comprobante documenta combustible, envío, transporte, recojo u otro gasto operativo. No genera stock ni modifica la numeración de compras.</td></tr>
    </table>
    <table class="signatures"><tr><td><div class="line"></div>Registró</td><td><div class="line"></div>Revisó</td></tr></table>
    <div class="footer">Generado el {{ $generadoEn->format('d/m/Y H:i') }} · {{ $brandName }}</div>
</div>
</body>
</html>
