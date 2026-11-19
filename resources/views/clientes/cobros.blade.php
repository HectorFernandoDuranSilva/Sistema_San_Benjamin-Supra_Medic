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

<form class="card" method="post" action="{{ route('clientes.cobros.store') }}" style="margin-bottom:16px;">
    @csrf
    <h2>Registrar pago por cliente</h2>
    <p class="muted">El importe se aplicará a la factura o nota seleccionada. Si deja automático, se aplicará desde la deuda más antigua.</p>
    <div class="row">
        <div class="field"><label>Cliente</label><select name="cliente_id" required><option value="">Seleccione</option>@foreach($clientes as $cliente)<option value="{{ $cliente->id }}" @selected(old('cliente_id') == $cliente->id)>{{ $cliente->nombre_mostrable }}</option>@endforeach</select></div>
        <div class="field"><label>Factura o nota específica</label><select name="venta_id" id="cobro-venta"><option value="">Automático: deuda más antigua</option>@foreach($documentos as $documento)<option value="{{ $documento->id }}" data-cliente-id="{{ $documento->cliente_id }}" @selected(old('venta_id') == $documento->id)>{{ $documento->numero_venta }} · {{ $documento->cliente?->nombre_mostrable }} · saldo Bs {{ number_format($documento->saldo_actual, 2) }}</option>@endforeach</select></div>
        <div class="field"><label>Importe Bs</label><input name="importe" type="number" step="0.01" min="0.01" value="{{ old('importe') }}" required></div>
        <div class="field"><label>Fecha</label><input name="fecha" type="date" value="{{ old('fecha', now()->toDateString()) }}" required></div>
        <div class="field"><label>Medio</label><select name="medio" required><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="deposito">Depósito</option></select></div>
        <div class="field"><label>Referencia</label><input name="referencia" maxlength="255" value="{{ old('referencia') }}"></div>
    </div>
    <button class="btn">Registrar pago</button>
</form>

<form class="card" method="post" action="{{ route('clientes.cobros.finiquitar') }}" style="margin-bottom:16px;" data-glass-confirm="¿Confirmar el finiquito de toda la cuenta seleccionada? Se registrará la regularización y el saldo quedará en Bs 0.00.">
    @csrf
    <h2>Finiquitar cuenta</h2>
    <p class="muted">Seleccione el cliente. El sistema regularizará todas sus facturas y notas pendientes, incluyendo cuotas, sin borrar documentos ni cobros anteriores.</p>
    <div class="row">
        <div class="field"><label>Cliente</label><select name="cliente_id" required><option value="">Seleccione</option>@foreach($clientes as $cliente)<option value="{{ $cliente->id }}">{{ $cliente->nombre_mostrable }}</option>@endforeach</select></div>
        <input type="hidden" name="confirmar" value="1">
    </div>
    <button class="btn" type="submit">Finiquitar cuenta</button>
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

@push('scripts')
<script>
(function () {
    const cliente = document.querySelector('select[name="cliente_id"]');
    const venta = document.getElementById('cobro-venta');
    if (!cliente || !venta) return;

    function filtrarDocumentos() {
        const clienteId = cliente.value;
        let seleccionValida = venta.value === '';
        venta.querySelectorAll('option[data-cliente-id]').forEach(function (option) {
            const visible = !clienteId || option.dataset.clienteId === clienteId;
            option.hidden = !visible;
            if (option.value === venta.value && visible) seleccionValida = true;
        });
        if (!seleccionValida) venta.value = '';
    }

    cliente.addEventListener('change', filtrarDocumentos);
    filtrarDocumentos();
}());
</script>
@endpush
