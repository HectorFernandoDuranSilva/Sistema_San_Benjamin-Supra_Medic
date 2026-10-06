@extends('layouts.app')

@section('content')
<div class="top"><h1>Inventario</h1><div class="actions"><a class="btn" href="{{ route('productos.create') }}">Crear producto</a><a class="btn secondary" href="{{ route('productos.index') }}">Productos</a><a class="btn secondary" href="{{ route('inventario.movimientos') }}">Ver movimientos</a><a class="btn secondary" href="{{ route('inventario.lotes.index') }}">Lotes y vencimientos</a></div></div>
<form class="card" method="get" style="margin-bottom:12px;">
    <div class="row">
        <div class="field"><label>Buscar producto</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Código o nombre"></div>
        <div class="field"><label>Stock desde</label><input type="number" step="0.01" min="0" name="stock_desde" value="{{ $filters['stock_desde'] ?? '' }}" placeholder="Ej. 0"></div>
        <div class="field"><label>Stock hasta</label><input type="number" step="0.01" min="0" name="stock_hasta" value="{{ $filters['stock_hasta'] ?? '' }}" placeholder="Ej. 10"></div>
        <div class="field" style="justify-content:end;"><button class="btn secondary">Filtrar</button></div>
    </div>
</form>
<div class="card"><table><tr><th>Código</th><th>Producto</th><th>Marca</th><th>Procedencia</th><th>Stock actual</th><th>Stock mínimo</th><th>Alerta</th><th>Acciones</th></tr>
@foreach($productos as $p)
    <tr>
        <td>{{ $p->codigo }}</td>
        <td>{{ $p->nombre }}</td>
        <td>{{ $p->marca ?: '-' }}</td>
        <td>{{ $p->procedencia ?: '-' }}</td>
        <td>{{ $p->stock_actual }}</td>
        <td>{{ $p->stock_minimo }}</td>
        <td>{{ $p->stock_actual <= $p->stock_minimo ? 'Stock bajo' : '' }}</td>
        <td>
            <div class="actions" style="gap:8px;">
                <a class="btn secondary" href="{{ route('productos.show', $p) }}">Ver</a>
                <a class="btn secondary" href="{{ route('productos.edit', $p) }}">Editar</a>
                <form method="post" action="{{ route('productos.destroy', $p) }}" data-glass-confirm="¿Desea eliminar este ítem de inventario? Si tiene movimientos, compras o ventas se desactivará para conservar el historial.">
                    @csrf
                    @method('delete')
                    <input type="hidden" name="return_route" value="inventario.index">
                    <input name="totp_code" inputmode="numeric" maxlength="32" placeholder="Código Authenticator" required>
                    <button class="btn danger" type="submit">Eliminar</button>
                </form>
            </div>
            <details class="force-delete">
                <summary>Eliminar definitivo</summary>
                <form method="post" action="{{ route('productos.force-destroy', $p) }}" data-glass-confirm="Esta acción borrara definitivamente el ítem y sus referencias directas en inventario, compras y ventas. ¿Desea continuar?">
                    @csrf
                    @method('delete')
                    <input type="hidden" name="return_route" value="inventario.index">
                    <input name="totp_code" inputmode="numeric" maxlength="32" placeholder="Código Authenticator" required>
                    <button class="btn force-danger" type="submit">Forzar eliminacion</button>
                </form>
            </details>
        </td>
    </tr>
@endforeach
</table><div class="pagination">{{ $productos->links() }}</div></div>
@endsection
