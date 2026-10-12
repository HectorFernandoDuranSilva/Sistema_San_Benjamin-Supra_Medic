@extends('layouts.app')

@section('content')
<div class="top">
    <h1>{{ $venta->tipo_documento === 'nota_entrega' ? 'Nota de entrega' : 'Venta' }} {{ $venta->numero_venta }}</h1>
    <div class="actions">
        @if($venta->tipo_documento === 'nota_entrega' && auth()->user()->hasPermission('reportes'))
            <a class="btn secondary" href="{{ route('reportes.documentos') }}#notas-entrega">Notas/Facturas</a>
        @endif
        <a class="btn secondary" href="{{ route('ventas.recibo', $venta) }}">Vista recibo</a>
        <a class="btn secondary" href="{{ route('ventas.pdf', $venta) }}">PDF</a>
        <a class="btn secondary" href="{{ route('ventas.historial.edit', $venta) }}">Editar cabecera</a>
        @if($venta->estado !== 'anulada')
            <form method="post" action="{{ route('ventas.anular', $venta) }}" data-glass-confirm="¿Anular este documento y devolver el stock?">@csrf<input name="totp_code" inputmode="numeric" maxlength="32" placeholder="Código Authenticator" required><button class="btn danger">Anular</button></form>
        @endif
        <form method="post" action="{{ route('ventas.destroy', $venta) }}" data-glass-confirm="La eliminación es definitiva. Se restaurará el inventario únicamente si este documento seguía registrado. ¿Desea eliminarlo?">
            @csrf
            @method('DELETE')
            <input name="totp_code" inputmode="numeric" maxlength="32" placeholder="Código Authenticator" required>
            <button class="btn danger">Eliminar definitivamente</button>
        </form>
    </div>
</div>

<div class="card">
    @if($venta->permitir_salida_sin_stock)
        @php($faltantes = $venta->detalles->filter(fn ($detalle) => (float) ($detalle->producto?->stock_actual ?? 0) < 0))
        <div class="alert-item" style="margin-bottom:14px;">
            <strong><span>{{ $faltantes->isNotEmpty() ? 'Pendiente de regularización de inventario' : 'Stock regularizado' }}</span><span>{{ $faltantes->isNotEmpty() ? 'Pendiente' : 'Regularizada' }}</span></strong>
            <small>Motivo: {{ $venta->motivo_salida_sin_stock }}@if($faltantes->isNotEmpty()) · Faltante actual: {{ $faltantes->map(fn ($detalle) => $detalle->producto?->codigo . ' ' . number_format(abs((float) $detalle->producto?->stock_actual), 2))->join(', ') }}@endif</small>
        </div>
    @endif
    <p>
        <strong>Orden:</strong> {{ $venta->nombre_comercial ?? 'Sin nombre comercial' }} |
        <strong>Documento:</strong> {{ $venta->tipo_documento === 'nota_entrega' ? 'Nota de entrega' : 'Factura' }} |
        <strong>Fecha:</strong> {{ $venta->fecha->format('d/m/Y') }} |
        <strong>Factura:</strong> {{ $venta->factura ?: '-' }} |
        <strong>NIT:</strong> {{ $venta->nit_cliente ?: '-' }} |
        <strong>Cliente:</strong> {{ $venta->cliente?->nombre_mostrable ?? 'Sin cliente' }} |
        <strong>Estado:</strong> {{ $venta->estado }}
    </p>
    <p>
        <strong>Lista:</strong> {{ $venta->lista_precios }} |
        <strong>Descuento %:</strong> {{ number_format($venta->descuento_porcentaje, 2) }} |
        <strong>Comentario:</strong> {{ $venta->observacion ?: '-' }}
    </p>

    <table>
        <tr><th>Código</th><th>Producto</th><th>Marca</th><th>Procedencia</th><th>Cantidad</th><th>Precio</th><th>Desc</th><th>Subtotal</th><th>Impuesto</th><th>Envío</th><th>Otros</th><th>Total</th></tr>
        @foreach($venta->detalles as $d)
            <tr>
                <td>{{ $d->codigo_producto }}</td>
                <td>{{ $d->nombre_producto }}</td>
                <td>{{ $d->producto?->marca ?: '-' }}</td>
                <td>{{ $d->producto?->procedencia ?: '-' }}</td>
                <td>{{ $d->cantidad }}</td>
                <td>Bs {{ number_format($d->precio_unitario, 2) }}</td>
                <td>Bs {{ number_format($d->descuento, 2) }}</td>
                <td>Bs {{ number_format($d->subtotal, 2) }}</td>
                <td>Bs {{ number_format($d->impuesto, 2) }}</td>
                <td>Bs {{ number_format($d->costo_envio, 2) }}</td>
                <td>Bs {{ number_format($d->otros_costos, 2) }}</td>
                <td>Bs {{ number_format($d->total, 2) }}</td>
            </tr>
            @if($d->lotes->isNotEmpty())
                <tr>
                    <td colspan="12">
                        <strong>Lotes usados:</strong>
                        @foreach($d->lotes as $consumo)
                            {{ number_format($consumo->cantidad, 2) }} unid. lote {{ $consumo->lote?->codigo_lote ?: 'sin lote' }} vence {{ optional($consumo->fecha_vencimiento)->format('d/m/Y') ?? 'sin fecha' }}@if(!$loop->last), @endif
                        @endforeach
                    </td>
                </tr>
            @endif
        @endforeach
    </table>

    <h2 style="text-align:right;margin-top:12px;">
        Subtotal Bs {{ number_format($venta->subtotal, 2) }} |
        Desc Bs {{ number_format($venta->descuento, 2) }} |
        Imp Bs {{ number_format($venta->impuesto_total, 2) }} |
        Envío Bs {{ number_format($venta->costo_envio_total, 2) }} |
        Otros Bs {{ number_format($venta->otros_costos_total, 2) }} |
        Total Bs {{ number_format($venta->total, 2) }}
    </h2>
</div>
@include('ventas.partials.payments', ['cuotasDisponibles' => $cuotasDisponibles ?? true])
@endsection

