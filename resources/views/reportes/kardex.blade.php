@extends('layouts.app')

@section('content')
<div class="top">
    <h1>Kardex</h1>
    <div class="actions">
        <a class="btn secondary" href="{{ route('clientes.historial') }}">Historial por cliente</a>
        <a class="btn secondary" href="{{ route('reportes.inventario') }}">Inventario</a>
        <a class="btn secondary" href="{{ route('reportes.guardados') }}">Reportes guardados</a>
        <a class="btn" href="{{ route('reportes.kardex.pdf', request()->query()) }}">Descargar PDF</a>
    </div>
</div>

<form class="actions" method="get" style="margin-bottom:12px;">
    <input type="date" name="desde" value="{{ request('desde') }}">
    <input type="date" name="hasta" value="{{ request('hasta') }}">
    <select name="producto_id">
        <option value="">Producto</option>
        @foreach($productos as $producto)
            <option value="{{ $producto->id }}" @selected(request('producto_id') == $producto->id)>{{ $producto->codigo }} {{ $producto->nombre }}</option>
        @endforeach
    </select>
    <select name="cliente_id">
        <option value="">Cliente</option>
        @foreach($clientes as $cliente)
            <option value="{{ $cliente->id }}" @selected(request('cliente_id') == $cliente->id)>{{ $cliente->nombre_mostrable }}{{ $cliente->documento ? ' - '.$cliente->documento : '' }}</option>
        @endforeach
    </select>
    <select name="tipo_movimiento">
        <option value="">Tipo</option>
        @foreach(['entrada', 'salida', 'ajuste_positivo', 'ajuste_negativo', 'anulacion', 'devolucion'] as $tipo)
            <option value="{{ $tipo }}" @selected(request('tipo_movimiento') === $tipo)>{{ ucfirst(str_replace('_', ' ', $tipo)) }}</option>
        @endforeach
    </select>
    <button class="btn secondary">Filtrar</button>
</form>

@if($clienteSeleccionado)
    <div class="notice" style="margin-bottom: 12px;">
        Historial de cliente: <strong>{{ $clienteSeleccionado->nombre_mostrable }}</strong>. Se muestran sus ventas y los movimientos relacionados.
    </div>
@endif

<form class="actions" method="post" action="{{ route('reportes.guardados.store') }}" style="margin-bottom:12px;">
    @csrf
    <input name="titulo" value="Kardex {{ now()->format('d/m/Y') }}">
    <input type="hidden" name="tipo" value="kardex">
    <input type="hidden" name="ruta" value="{{ request()->getRequestUri() }}">
    <input name="observacion" placeholder="Observación">
    <button class="btn secondary">Guardar reporte actual</button>
</form>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Fecha / hora de registro</th>
                <th>Producto</th>
                <th>Tipo</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Precio/Costo</th>
                <th>Total linea</th>
                <th>Stock anterior</th>
                <th>Stock nuevo</th>
                <th>Lote</th>
                <th>Referencia</th>
                <th>Tercero</th>
                <th>Usuario</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movimientos as $m)
                <tr>
                    @php($horaRegistro = $m->created_at ?: $m->fecha_movimiento)
                    <td>{{ $m->fecha_movimiento->format('d/m/Y') }}<br><small>{{ $horaRegistro->format('H:i') }}</small></td>
                    <td>{{ $m->producto?->codigo }} {{ $m->producto?->nombre }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $m->tipo_movimiento)) }}</td>
                    <td>{{ in_array($m->tipo_movimiento, ['entrada', 'ajuste_positivo', 'devolucion', 'anulacion'], true) ? number_format($m->cantidad, 2) : '-' }}</td>
                    <td>{{ in_array($m->tipo_movimiento, ['salida', 'ajuste_negativo'], true) ? number_format($m->cantidad, 2) : '-' }}</td>
                    <td>{{ $m->kardex_precio_unitario !== null ? 'Bs ' . number_format((float) $m->kardex_precio_unitario, 2) : '-' }}</td>
                    <td>{{ $m->kardex_total_linea !== null ? 'Bs ' . number_format((float) $m->kardex_total_linea, 2) : '-' }}</td>
                    <td>{{ number_format($m->stock_anterior, 2) }}</td>
                    <td>{{ number_format($m->stock_nuevo, 2) }}</td>
                    <td>{{ $m->kardex_lote }}</td>
                    <td>{{ $m->kardex_documento }}</td>
                    <td>{{ $m->kardex_tercero }}</td>
                    <td>{{ $m->usuario?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="13">No hay movimientos para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="pagination">{{ $movimientos->links() }}</div>
</div>
@endsection

