@extends('layouts.app')

@section('content')
<div class="top">
    <div><h1>Cuentas por cobrar</h1><p class="muted">Facturas y notas con saldo pendiente. Abra un documento para registrar una cuota, un pago parcial o finiquitarlo.</p></div>
    <div class="actions"><a class="btn secondary" href="{{ route('reportes.documentos') }}">Notas/Facturas</a></div>
</div>

<form class="card" method="get" style="margin-bottom:16px;">
    <div class="row">
        <div class="field"><label>Buscar cliente o documento</label><input name="buscar" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Número, factura, NIT o cliente"></div>
        <div class="field"><label>Documento</label><select name="tipo_documento"><option value="">Todos</option><option value="factura" @selected(($filtros['tipo_documento'] ?? '') === 'factura')>Facturas</option><option value="nota_entrega" @selected(($filtros['tipo_documento'] ?? '') === 'nota_entrega')>Notas de entrega</option></select></div>
        <div class="field"><label>Nombre comercial</label><select name="nombre_comercial"><option value="">Todos</option><option value="San Benjamin" @selected(($filtros['nombre_comercial'] ?? '') === 'San Benjamin')>San Benjamin</option><option value="Supra Medic" @selected(($filtros['nombre_comercial'] ?? '') === 'Supra Medic')>Supra Medic</option></select></div>
    </div>
    <button class="btn secondary">Filtrar</button>
</form>

<div class="card">
    <div class="top"><h2>Documentos por cobrar</h2><strong>{{ $documentos->count() }} pendientes</strong></div>
    <table>
        <tr><th>Documento</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Cobrado</th><th>Saldo</th><th>Cuotas pendientes</th><th>Estado</th><th>Acción</th></tr>
        @forelse($documentos as $venta)
            <tr>
                <td><strong>{{ $venta->numero_venta }}</strong><br><small>{{ $venta->tipo_documento === 'nota_entrega' ? 'Nota de entrega' : 'Factura' }}{{ $venta->factura ? ' · '.$venta->factura : '' }}</small></td>
                <td>{{ $venta->cliente?->nombre_mostrable ?? 'Sin cliente' }}<br><small>{{ $venta->nombre_comercial ?: '-' }}</small></td>
                <td>{{ $venta->fecha->format('d/m/Y') }}</td>
                <td>Bs {{ number_format($venta->total, 2) }}</td>
                <td>Bs {{ number_format($venta->cobrado_actual, 2) }}</td>
                <td><strong>Bs {{ number_format($venta->saldo_actual, 2) }}</strong></td>
                <td>{{ $venta->cuotas_pendientes ?: 'Pago único' }}</td>
                <td><span class="pill">{{ $venta->estado_pago }}</span></td>
                <td><a class="btn secondary" href="{{ route($venta->tipo_documento === 'nota_entrega' ? 'notas.show' : 'ventas.show', $venta) }}#cobros">Registrar cobro</a></td>
            </tr>
        @empty
            <tr><td colspan="9">No hay facturas ni notas por cobrar con los filtros seleccionados.</td></tr>
        @endforelse
    </table>
</div>
@endsection
