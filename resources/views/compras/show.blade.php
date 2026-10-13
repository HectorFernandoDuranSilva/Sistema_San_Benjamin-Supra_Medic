@extends('layouts.app')

@section('content')
<div class="top"><h1>Compra {{ $compra->numero_compra }}</h1><div class="actions"><a class="btn secondary" href="{{ route('compras.recibo', $compra) }}">Vista recibo</a><a class="btn secondary" href="{{ route('compras.pdf', $compra) }}">PDF</a>@if(auth()->user()->isAdmin())<a class="btn secondary" href="{{ route('compras.historial.edit', $compra) }}">Editar cabecera</a>@endif</div></div>
<div class="card"><p><strong>Fecha:</strong> {{ $compra->fecha->format('d/m/Y') }} | <strong>Documento:</strong> {{ $compra->tipo_documento === 'nota_entrega' ? 'Nota de entrega' : 'Factura' }} | <strong>Factura:</strong> {{ $compra->factura ?: 'Sin factura' }} | <strong>NIT:</strong> {{ $compra->nit_proveedor ?: 'Sin NIT' }} | <strong>Proveedor:</strong> {{ $compra->proveedor?->nombre ?? 'Sin proveedor' }}</p>
<table>
    <tr><th>Código</th><th>Producto</th><th>Marca</th><th>Procedencia</th><th>Cantidad</th><th>Costo factura</th><th>Costo neto</th><th>Utilidad</th><th>Desc.</th><th>Lote</th><th>Vencimiento</th><th>Subtotal</th><th>Impuesto</th><th>Total</th><th>Acciones</th></tr>
    @foreach($compra->detalles as $d)
        <tr>
            <td>{{ $d->codigo_producto }}</td>
            <td>{{ $d->nombre_producto }}</td>
            <td>{{ $d->producto?->marca ?: '-' }}</td>
            <td>{{ $d->producto?->procedencia ?: '-' }}</td>
            <td>{{ $d->cantidad }}</td>
            <td>Bs {{ number_format($d->costo_unitario, 2) }}</td>
            <td>Bs {{ number_format($d->costo_neto_unitario ?? $d->costo_unitario, 2) }}</td>
            <td>{{ number_format($d->porcentaje_utilidad, 2) }}%</td>
            <td>{{ number_format($d->descuento_porcentaje ?? 0, 2) }}%<br>Bs {{ number_format($d->descuento ?? 0, 2) }}</td>
            <td>{{ $d->codigo_lote ?: '-' }}</td>
            <td>{{ optional($d->fecha_vencimiento)->format('d/m/Y') ?? '-' }}</td>
            <td>Bs {{ number_format($d->subtotal, 2) }}</td>
            <td>Bs {{ number_format($d->impuesto, 2) }}</td>
            <td>Bs {{ number_format($d->total, 2) }}</td>
            <td>
                @if($d->lote)
                    <a class="btn secondary" href="{{ route('inventario.lotes.index', ['producto_id' => $d->producto_id, 'estado' => 'activo']) }}">Editar lote</a>
                @else
                    <span class="muted">Sin lote</span>
                @endif
            </td>
        </tr>
    @endforeach
</table>
<h2 style="text-align:right;margin-top:12px;">Total Bs {{ number_format($compra->total, 2) }}</h2></div>
@include('compras.partials.payments')
@endsection

