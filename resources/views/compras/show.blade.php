@extends('layouts.app')

@section('content')
<div class="top"><h1>Compra {{ $compra->numero_compra }}</h1><div class="actions"><a class="btn secondary" href="{{ route('compras.recibo', $compra) }}">Vista recibo</a><a class="btn secondary" href="{{ route('compras.pdf', $compra) }}">PDF</a>@if(auth()->user()->hasPermission('compras'))<a class="btn secondary" href="{{ route('compras.historial.edit', $compra) }}">Editar compra</a>@endif @if($compra->estado !== 'anulada')<form method="post" action="{{ route('compras.anular', $compra) }}" data-glass-confirm="¿Anular esta compra y retirar del stock las entradas registradas?">@csrf<button class="btn danger">Anular</button></form>@endif<form method="post" action="{{ route('compras.destroy', $compra) }}" data-glass-confirm="La eliminación es definitiva y requiere Google Authenticator. ¿Desea eliminar esta compra?"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="_method" value="DELETE"><input name="totp_code" inputmode="numeric" autocomplete="one-time-code" maxlength="32" placeholder="Código Authenticator" required><button class="btn danger">Eliminar definitivamente</button></form></div></div>
<div class="card"><p><strong>Fecha:</strong> {{ $compra->fecha->format('d/m/Y') }} | <strong>Documento:</strong> {{ $compra->tipo_documento === 'nota_entrega' ? 'Nota de entrega' : 'Factura' }} | <strong>Factura:</strong> {{ $compra->factura ?: 'Sin factura' }} | <strong>NIT:</strong> {{ $compra->nit_proveedor ?: 'Sin NIT' }} | <strong>Proveedor:</strong> {{ $compra->proveedor?->nombre ?? 'Sin proveedor' }}</p>
@if($compra->estado === 'anulada')<div class="notice" style="margin-bottom:14px;"><strong>Anulada por:</strong> {{ $compra->auditorias->first()?->usuario?->name ?? $compra->auditorias->first()?->usuario?->username ?? 'Usuario no disponible' }}</div>@endif
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
@if($compra->gastos->isNotEmpty())
    <h2>Gastos asociados</h2>
    <table>
        <tr><th>Tipo</th><th>Operación</th><th>Respaldo</th><th>Número</th><th>Fecha</th><th>Descripción</th><th>Importe</th></tr>
        @foreach($compra->gastos as $gasto)
            <tr><td>{{ ucfirst($gasto->tipo) }}</td><td>{{ ucfirst($gasto->modalidad) }}</td><td>{{ ucfirst(str_replace('_', ' ', $gasto->documento_tipo)) }}</td><td>{{ $gasto->documento_numero ?: '-' }}</td><td>{{ $gasto->fecha->format('d/m/Y') }}</td><td>{{ $gasto->descripcion ?: '-' }}</td><td>Bs {{ number_format($gasto->importe, 2) }}</td></tr>
        @endforeach
    </table>
@endif
<h2 style="text-align:right;margin-top:12px;">Total Bs {{ number_format($compra->total, 2) }}</h2></div>
@include('compras.partials.payments')
@endsection

