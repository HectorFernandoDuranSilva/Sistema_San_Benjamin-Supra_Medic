@extends('layouts.app')

@section('content')
<div class="top"><h1>Productos</h1><div class="actions"><a class="btn" href="{{ route('productos.create') }}">Crear producto</a></div></div>
<div class="notice">Tipo de cambio vigente para sugeridos: Bs {{ number_format($tipoCambioValor, 2) }}@if($tipoCambioActual) - {{ $tipoCambioActual->fuente }} {{ $tipoCambioActual->fecha->format('d/m/Y') }}@endif.</div>
<form class="actions" method="get" style="margin-bottom:12px;">
    <input name="q" value="{{ request('q') }}" placeholder="Buscar por código o nombre">
    <select name="estado" style="width:auto;">
        <option value="activos" @selected(($estado ?? 'activos') === 'activos')>Activos</option>
        <option value="inactivos" @selected(($estado ?? 'activos') === 'inactivos')>Inactivos</option>
        <option value="todos" @selected(($estado ?? 'activos') === 'todos')>Todos</option>
    </select>
    <button class="btn secondary">Buscar</button>
</form>
<div class="card">
<table>
    <tr><th>Código</th><th>Nombre</th><th>Categoría</th><th>Moneda</th><th>Ingreso USD</th><th>Cambio</th><th>%</th><th>Costo actual Bs</th><th>Sugerido Bs</th><th>Venta Bs</th><th>Mínimo</th><th>Stock</th><th>Estado</th><th>Acciones</th></tr>
    @foreach($productos as $producto)
        <tr>
            <td>{{ $producto->codigo }}</td><td>{{ $producto->nombre }}</td><td>{{ $producto->categoria?->nombre }}</td>
            <td>{{ ($producto->moneda_costo ?? 'usd') === 'bs' ? 'Bs' : 'USD' }}</td><td>{{ ($producto->moneda_costo ?? 'usd') === 'bs' ? '-' : '$ ' . number_format($producto->precio_ingreso_usd, 2) }}</td><td>{{ number_format($producto->tipo_cambio, 4) }}</td><td>{{ number_format($producto->porcentaje_utilidad, 2) }}%</td>
            <td>Bs {{ number_format((float) $producto->costo_reposicion_actual, 2) }}</td><td>{{ $producto->precio_sugerido_reposicion !== null ? 'Bs ' . number_format((float) $producto->precio_sugerido_reposicion, 2) : '-' }}</td><td>Bs {{ number_format($producto->precio_venta, 2) }}</td>
            <td>{{ $producto->stock_minimo }}</td><td>{{ $producto->stock_actual }}</td><td>{{ $producto->activo ? 'Activo' : 'Inactivo' }}</td>
            <td>
                <div class="actions" style="gap:8px;">
                    <a class="btn secondary" href="{{ route('productos.show', $producto) }}">Ver</a>
                    <a class="btn secondary" href="{{ route('productos.edit', $producto) }}">Editar</a>
                    <form method="post" action="{{ route('productos.destroy', $producto) }}" data-glass-confirm="¿Desea eliminar este producto? Si tiene movimientos, compras o ventas se desactivará para conservar el historial.">
                        @csrf
                        @method('delete')
                        <input type="hidden" name="return_route" value="productos.index">
                        <input name="totp_code" inputmode="numeric" maxlength="32" placeholder="Código Authenticator" required>
                        <button class="btn danger" type="submit">Eliminar</button>
                    </form>
                </div>
                <details class="force-delete">
                    <summary>Eliminar definitivo</summary>
                    <form method="post" action="{{ route('productos.force-destroy', $producto) }}" data-glass-confirm="Esta acción borrara definitivamente el producto y sus referencias directas en inventario, compras y ventas. ¿Desea continuar?">
                        @csrf
                        @method('delete')
                        <input type="hidden" name="return_route" value="productos.index">
                        <input name="totp_code" inputmode="numeric" maxlength="32" placeholder="Código Authenticator" required>
                        <button class="btn force-danger" type="submit">Forzar eliminacion</button>
                    </form>
                </details>
            </td>
        </tr>
    @endforeach
</table>
<div class="pagination">{{ $productos->links() }}</div>
</div>
@endsection
