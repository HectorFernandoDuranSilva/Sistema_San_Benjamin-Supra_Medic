@extends('layouts.app')

@section('content')
<div class="top">
    <h1>Producto {{ $producto->codigo }}</h1>
    <div class="actions">
        <a class="btn secondary" href="{{ route('productos.edit', $producto) }}">Editar producto</a>
        <a class="btn secondary" href="{{ route('inventario.lotes.index', ['producto_id' => $producto->id]) }}">Editar lotes</a>
        <a class="btn secondary" href="{{ route('productos.index') }}">Productos</a>
    </div>
</div>

<div class="grid cols-4" style="margin-bottom:16px;">
    <div class="metric"><span>Stock actual</span><strong>{{ number_format($producto->stock_actual, 2) }}</strong></div>
    <div class="metric"><span>Stock mínimo</span><strong>{{ number_format($producto->stock_minimo, 2) }}</strong></div>
    <div class="metric"><span>Costo de reposición actual</span><strong>Bs {{ number_format((float) $producto->costo_reposicion_actual, 2) }}</strong></div>
    <div class="metric"><span>Precio venta</span><strong>Bs {{ number_format($producto->precio_venta, 2) }}</strong></div>
</div>

<section style="margin-bottom:20px;">
    <h2>{{ $producto->nombre }}</h2>
    <dl>
        <dt>Marca</dt><dd>{{ $producto->marca ?: 'Pendiente de completar' }}</dd>
        <dt>Procedencia</dt><dd>{{ $producto->procedencia ?: 'Pendiente de completar' }}</dd>
    </dl>
</section>

<section style="margin-bottom:20px;">
    <h2>Costos y precios</h2>
    <table>
        <tr><th>Última compra registrada</th><th>Costo promedio</th><th>Valor del stock</th><th>Recargo sobre costo</th></tr>
        <tr>
            <td>{{ $producto->ultimo_costo_compra === null ? 'Sin registro' : 'Bs ' . number_format($producto->ultimo_costo_compra, 2) }}</td>
            <td>{{ $producto->costo_promedio === null ? 'Pendiente de valoración' : 'Bs ' . number_format($producto->costo_promedio, 2) }}</td>
            <td>{{ $producto->valor_inventario === null ? 'Pendiente de valoración' : 'Bs ' . number_format($producto->valor_inventario, 2) }}</td>
            <td>{{ number_format($producto->porcentaje_utilidad, 2) }}%</td>
        </tr>
    </table>
    <div class="actions" style="margin-top:12px;">
        @foreach(['promedio' => $producto->precio_sugerido, 'reposicion' => $producto->precio_sugerido_reposicion] as $base => $precio)
            @if($precio > 0)
                <form method="post" action="{{ route('productos.precio.aprobar', $producto) }}" data-glass-confirm="¿Confirmar este precio de venta para operaciones nuevas?">
                    @csrf
                    <input type="hidden" name="base" value="{{ $base }}"><input type="hidden" name="precio_esperado" value="{{ $precio }}">
                    <span>{{ $base === 'promedio' ? 'Sugerido por promedio' : 'Sugerido por reposición' }}: Bs {{ number_format($precio, 2) }}</span>
                    @if(auth()->user()->isAdmin())<button class="btn secondary">Confirmar precio</button>@endif
                </form>
            @endif
        @endforeach
    </div>
</section>

<section style="margin-bottom:20px;">
    <h2>Histórico de compras del producto</h2>
    <table>
        <tr><th>Fecha de factura</th><th>Compra</th><th>Factura</th><th>Proveedor</th><th>Cantidad</th><th>Costo unitario</th><th>Importe</th></tr>
        @forelse($comprasHistoricas as $detalle)
            <tr><td>{{ $detalle->compra?->fecha?->format('d/m/Y') }}</td>
                <td>@if($detalle->compra)<a href="{{ route('compras.show', $detalle->compra) }}">{{ $detalle->compra->numero_compra }}</a>@endif</td>
                <td>{{ $detalle->compra?->factura ?: '-' }}</td><td>{{ $detalle->compra?->proveedor?->nombre ?: '-' }}</td>
                <td>{{ number_format($detalle->cantidad, 2) }}</td><td>Bs {{ number_format($detalle->costo_unitario, 2) }}</td><td>Bs {{ number_format($detalle->total, 2) }}</td></tr>
        @empty<tr><td colspan="7">Sin compras registradas.</td></tr>@endforelse
    </table>
    {{ $comprasHistoricas->links() }}
</section>

<section class="card" style="margin-bottom:16px;">
    <h2>Datos del producto</h2>
    <table>
        <tr><th>Código</th><td>{{ $producto->codigo }}</td><th>Nombre</th><td>{{ $producto->nombre }}</td></tr>
        <tr><th>Categoría</th><td>{{ $producto->categoria?->nombre ?? 'Sin categoría' }}</td><th>Medida</th><td>{{ $producto->medida ?: '-' }}</td></tr>
        <tr><th>Ubicación</th><td>{{ $producto->ubicacion ?: '-' }}</td><th>Estado</th><td>{{ $producto->activo ? 'Activo' : 'Inactivo' }}</td></tr>
        <tr><th>Descripción</th><td colspan="3">{{ $producto->descripcion ?: '-' }}</td></tr>
    </table>
</section>

<section class="card">
    <h2>Lotes asociados</h2>
    <table>
        <tr><th>Lote</th><th>Fecha de vencimiento</th><th>Cantidad inicial</th><th>Consumido</th><th>Disponible</th><th>Costo unitario</th><th>Estado</th></tr>
        @forelse($lotes as $lote)
            <tr>
                <td>{{ $lote->codigo_lote ?: 'Sin lote' }}</td>
                <td>{{ optional($lote->fecha_vencimiento)->format('d/m/Y') ?? '-' }}</td>
                <td>{{ number_format($lote->cantidad_inicial, 2) }}</td>
                <td>{{ number_format((float) ($lote->cantidad_consumida ?? 0), 2) }}</td>
                <td>{{ number_format($lote->cantidad_disponible, 2) }}</td>
                <td>Bs {{ number_format($lote->costo_unitario, 2) }}</td>
                <td>{{ ucfirst($lote->estado) }}</td>
            </tr>
        @empty
            <tr><td colspan="7">Este producto todavía no tiene lotes registrados.</td></tr>
        @endforelse
    </table>
</section>
@endsection
