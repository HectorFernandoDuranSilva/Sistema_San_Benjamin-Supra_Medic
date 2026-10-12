@extends('layouts.app')

@section('content')
<style>
    .lotes-table-scroll { overflow-x:auto; }
    .lotes-table { min-width:1280px; }
    .lotes-table tbody td { vertical-align:top; }
    .lotes-table td > input, .lotes-table td > select,
    .lotes-table td > form > input:not([type="hidden"]),
    .lotes-table .lote-actions > button { height:40px; margin:0; }
    .lotes-table td > form { margin:0; }
    .lotes-table .lote-primary { min-height:40px; display:flex; flex-direction:column; justify-content:center; }
    .lotes-table .lote-actions { display:flex; flex-direction:column; align-items:stretch; gap:8px; }
    .lotes-table .lote-actions .force-delete { margin:0; }
    .lotes-table .force-delete input { min-width:0; }
    .lotes-table td > small { display:block; margin-top:6px; }
    .lotes-table input[type="date"] { min-width:150px; }
</style>
<div class="top">
    <h1>Lotes y vencimientos</h1>
    <div class="actions">
        <a class="btn secondary" href="{{ route('inventario.index') }}">Inventario</a>
        <a class="btn secondary" href="{{ route('compras.create') }}">Registrar compra</a>
    </div>
</div>

<form class="card" method="get" style="margin-bottom:12px;">
    <div class="row">
        <div class="field">
            <label>Producto</label>
            <select name="producto_id">
                <option value="">Todos</option>
                @foreach($productos as $producto)
                    <option value="{{ $producto->id }}" @selected(request('producto_id') == $producto->id)>{{ $producto->codigo }} - {{ $producto->nombre }} | {{ $producto->marca ?: 'Sin marca' }} | {{ $producto->procedencia ?: 'Sin procedencia' }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Estado</label>
            <select name="estado">
                <option value="activo" @selected(request('estado', 'activo') === 'activo')>Activos</option>
                <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option>
                <option value="todos" @selected(request('estado') === 'todos')>Todos</option>
            </select>
        </div>
        <div class="field">
            <label>Vencimiento</label>
            <select name="vencimiento">
                <option value="">Todos</option>
                <option value="por_vencer" @selected(request('vencimiento') === 'por_vencer')>Por vencer ({{ $diasAlertaVencimiento }} días)</option>
                <option value="vencidos" @selected(request('vencimiento') === 'vencidos')>Vencidos</option>
                <option value="vigentes" @selected(request('vencimiento') === 'vigentes')>Vigentes</option>
                <option value="sin_fecha" @selected(request('vencimiento') === 'sin_fecha')>Sin fecha</option>
            </select>
        </div>
        <div class="field" style="justify-content:end;"><button class="btn secondary">Filtrar</button></div>
    </div>
</form>

<div class="card">
    <div class="lotes-table-scroll">
    <table class="lotes-table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Compra</th>
                <th>Lote</th>
                <th>Vencimiento</th>
                <th>Inicial</th>
                <th>Consumido</th>
                <th>Disponible</th>
                <th>Estado</th>
                <th>Observación</th>
                <th>Authenticator (guardar/eliminar)</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lotes as $lote)
                <tr>
                    <td>
                        <div class="lote-primary">
                        <strong>{{ $lote->producto?->codigo }}</strong>
                        <span>{{ $lote->producto?->nombre }}</span>
                        <small>{{ $lote->producto?->marca ?: 'Sin marca' }} | {{ $lote->producto?->procedencia ?: 'Sin procedencia' }}</small>
                        </div>
                    </td>
                    <td><div class="lote-primary">{{ $lote->compra?->numero_compra ?? '-' }}</div></td>
                    <td>
                        <input form="lote-edit-{{ $lote->id }}" name="codigo_lote" value="{{ $lote->codigo_lote }}">
                    </td>
                    <td>
                        <form id="lote-edit-{{ $lote->id }}" method="post" action="{{ route('inventario.lotes.update', $lote) }}" data-glass-confirm="Se actualizarán los datos del lote y su vencimiento relacionado. ¿Desea continuar?">
                            @csrf
                            @method('put')
                            <input type="date" name="fecha_vencimiento" value="{{ optional($lote->fecha_vencimiento)->format('Y-m-d') }}">
                        </form>
                        @if($lote->fecha_vencimiento)
                            @if($lote->fecha_vencimiento->lt(today()))
                                <span class="pill" style="margin-top:6px;">Vencido</span>
                            @elseif($lote->fecha_vencimiento->lte(today()->addDays($diasAlertaVencimiento)))
                                <span class="pill" style="margin-top:6px;">Por vencer</span>
                            @else
                                <span class="pill" style="margin-top:6px;">Vigente</span>
                            @endif
                        @else
                            <span class="pill" style="margin-top:6px;">Sin fecha</span>
                        @endif
                    </td>
                    <td><div class="lote-primary">{{ number_format($lote->cantidad_inicial, 2) }}</div></td>
                    <td><div class="lote-primary">{{ number_format((float) ($lote->cantidad_consumida ?? 0), 2) }}</div></td>
                    <td>
                        @php($maxDisponible = max(0, round((float) $lote->cantidad_inicial - (float) ($lote->cantidad_consumida ?? 0), 2)))
                        <input form="lote-edit-{{ $lote->id }}" type="number" step="0.01" min="0" max="{{ $maxDisponible }}" name="cantidad_disponible" value="{{ number_format((float) $lote->cantidad_disponible, 2, '.', '') }}">
                        <small class="muted">Máx. {{ number_format($maxDisponible, 2) }}</small>
                    </td>
                    <td>
                        <select form="lote-edit-{{ $lote->id }}" name="estado">
                            <option value="activo" @selected($lote->estado === 'activo')>Activo</option>
                            <option value="inactivo" @selected($lote->estado === 'inactivo')>Inactivo</option>
                        </select>
                    </td>
                    <td><input form="lote-edit-{{ $lote->id }}" name="observacion" value="{{ $lote->observacion }}"></td>
                    <td><input id="lote-totp-{{ $lote->id }}" form="lote-edit-{{ $lote->id }}" name="totp_code" inputmode="numeric" autocomplete="one-time-code" maxlength="32" placeholder="Código Authenticator" required></td>
                    <td>
                        <div class="lote-actions">
                            <button form="lote-edit-{{ $lote->id }}" class="btn secondary">Guardar</button>
                            <details class="force-delete">
                                <summary>Eliminar</summary>
                                <form class="lote-delete-form" method="post" action="{{ route('inventario.lotes.destroy', $lote) }}" data-code-source="lote-totp-{{ $lote->id }}" data-glass-confirm="Esta acción eliminará el lote. Si tiene movimientos históricos se conservará solo para mantener la trazabilidad. ¿Desea continuar?">
                                    @csrf
                                    @method('delete')
                                    <input type="hidden" name="totp_code">
                                    <button class="btn force-danger">Eliminar lote</button>
                                </form>
                            </details>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11">No hay lotes registrados todavia. Se crearan al registrar compras.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    <div class="pagination">{{ $lotes->links() }}</div>
</div>
<script>
document.querySelectorAll('.lote-delete-form').forEach(function (form) {
    form.addEventListener('submit', function () {
        const source = document.getElementById(form.dataset.codeSource);
        const target = form.querySelector('[name="totp_code"]');
        if (source && target) target.value = source.value;
    });
});
</script>
@endsection
