@extends('layouts.app')

@section('content')
<div class="top"><h1>{{ $venta->tipo_documento === 'nota_entrega' ? 'Editar nota de entrega' : 'Editar cabecera de venta' }} {{ $venta->numero_venta }}</h1></div>
<div class="notice">Edición segura: solo modifica datos de cabecera/facturación. No cambia productos, cantidades, totales ni movimientos de inventario.</div>
<div class="grid cols-2">
    <form class="card" method="post" action="{{ route('ventas.historial.update', $venta) }}">
        @csrf
        @method('put')
        <div class="row">
            <div class="field"><label>Nombre comercial</label><select name="nombre_comercial"><option value="San Benjamin" @selected(old('nombre_comercial', $venta->nombre_comercial) === 'San Benjamin')>San Benjamin</option><option value="Supra Medic" @selected(old('nombre_comercial', $venta->nombre_comercial) === 'Supra Medic')>Supra Medic</option></select></div>
            <div class="field"><label>Tipo documento</label><select name="tipo_documento"><option value="factura" @selected(old('tipo_documento', $venta->tipo_documento) === 'factura')>Factura</option><option value="nota_entrega" @selected(old('tipo_documento', $venta->tipo_documento) === 'nota_entrega')>Nota de entrega</option></select></div>
            <div class="field"><label>Fecha</label><input type="date" name="fecha" value="{{ old('fecha', $venta->fecha->format('Y-m-d')) }}" required></div>
            <div class="field"><label>Factura</label><input name="factura" value="{{ old('factura', $venta->factura) }}"></div>
            <div class="field"><label>NIT</label><input name="nit_cliente" value="{{ old('nit_cliente', $venta->nit_cliente) }}"></div>
        </div>
        <div class="row">
            <div class="field"><label>Cliente</label><select name="cliente_id"><option value="">Sin cliente</option>@foreach($clientes as $cliente)<option value="{{ $cliente->id }}" @selected(old('cliente_id', $venta->cliente_id)==$cliente->id)>{{ $cliente->nombre_mostrable }}</option>@endforeach</select></div>
            <div class="field"><label>Lista de precios</label><input name="lista_precios" value="{{ old('lista_precios', $venta->lista_precios) }}"></div>
            <div class="field"><label>Código de Google Authenticator</label><input name="totp_code" inputmode="numeric" maxlength="32" required></div>
        </div>
        <div class="field"><label>Comentario</label><textarea name="observacion">{{ old('observacion', $venta->observacion) }}</textarea></div>
        <div class="actions"><button class="btn">Guardar cabecera</button><a class="btn secondary" href="{{ route($venta->tipo_documento === 'nota_entrega' ? 'notas.show' : 'ventas.show', $venta) }}">Volver</a></div>
    </form>

    <section class="card">
        <div class="top">
            <h1 style="font-size:20px;">Vista previa</h1>
            <div class="actions">
                <a class="btn secondary" href="{{ route('ventas.recibo', $venta) }}" target="_blank">Abrir</a>
                <a class="btn secondary" href="{{ route('ventas.pdf', $venta) }}">PDF</a>
            </div>
        </div>
        <iframe title="Vista previa venta {{ $venta->numero_venta }}" src="{{ route('ventas.recibo', $venta) }}" style="width:100%;height:620px;border:1px solid rgba(103,190,217,.28);border-radius:8px;background:#fff;"></iframe>
    </section>
</div>
@endsection
