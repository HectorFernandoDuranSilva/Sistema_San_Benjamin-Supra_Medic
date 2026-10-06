@extends('layouts.app')

@section('content')
<div class="top"><h1>Editar cabecera de compra {{ $compra->numero_compra }}</h1></div>
<div class="notice">Edicion segura: solo modifica datos de cabecera/facturacion. No cambia productos, cantidades, totales ni movimientos de inventario.</div>
<div class="grid cols-2">
    <form class="card" method="post" action="{{ route('compras.historial.update', $compra) }}">
        @csrf
        @method('put')
        <div class="row">
            <div class="field"><label>Nombre comercial</label><select name="nombre_comercial"><option value="San Benjamin" @selected(old('nombre_comercial', $compra->nombre_comercial) === 'San Benjamin')>San Benjamin</option><option value="Supra Medic" @selected(old('nombre_comercial', $compra->nombre_comercial) === 'Supra Medic')>Supra Medic</option></select></div>
            <div class="field"><label>Tipo documento</label><select name="tipo_documento"><option value="factura" @selected(old('tipo_documento', $compra->tipo_documento) === 'factura')>Factura</option><option value="nota_entrega" @selected(old('tipo_documento', $compra->tipo_documento) === 'nota_entrega')>Nota de entrega</option></select></div>
            <div class="field"><label>Fecha</label><input type="date" name="fecha" value="{{ old('fecha', $compra->fecha->format('Y-m-d')) }}" required></div>
            <div class="field"><label>Factura</label><input name="factura" value="{{ old('factura', $compra->factura) }}"></div>
            <div class="field"><label>NIT</label><input name="nit_proveedor" value="{{ old('nit_proveedor', $compra->nit_proveedor) }}"></div>
        </div>
        <div class="row">
            <div class="field"><label>Proveedor</label><select name="proveedor_id"><option value="">Sin proveedor</option>@foreach($proveedores as $proveedor)<option value="{{ $proveedor->id }}" @selected(old('proveedor_id', $compra->proveedor_id)==$proveedor->id)>{{ $proveedor->nombre }}</option>@endforeach</select></div>
            <div class="field"><label>Código de Google Authenticator</label><input name="totp_code" inputmode="numeric" maxlength="32" required></div>
        </div>
        <div class="field"><label>Comentario</label><textarea name="observacion">{{ old('observacion', $compra->observacion) }}</textarea></div>
        <div class="actions"><button class="btn">Guardar cabecera</button><a class="btn secondary" href="{{ route('compras.show', $compra) }}">Volver</a></div>
    </form>

    <section class="card">
        <div class="top">
            <h1 style="font-size:20px;">Vista previa</h1>
            <div class="actions">
                <a class="btn secondary" href="{{ route('compras.recibo', $compra) }}" target="_blank">Abrir</a>
                <a class="btn secondary" href="{{ route('compras.pdf', $compra) }}">PDF</a>
            </div>
        </div>
        <iframe title="Vista previa compra {{ $compra->numero_compra }}" src="{{ route('compras.recibo', $compra) }}" style="width:100%;height:620px;border:1px solid rgba(103,190,217,.28);border-radius:8px;background:#fff;"></iframe>
    </section>
</div>
@endsection
