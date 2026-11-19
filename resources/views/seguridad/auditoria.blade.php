@extends('layouts.app')

@section('content')
<div class="top">
    <div>
        <h1>Auditoría</h1>
        <p class="muted">Historial de eliminaciones, anulaciones y demás cambios realizados en el sistema.</p>
    </div>
    <a class="btn secondary" href="{{ route('seguridad.dashboard') }}">Seguridad</a>
</div>

<form class="card actions" method="get" style="margin-bottom:16px;">
    <input name="buscar" value="{{ request('buscar') }}" placeholder="Usuario, descripción o registro">
    <select name="accion">
        <option value="">Todas las acciones</option>
        @foreach($acciones as $accion)<option value="{{ $accion }}" @selected(request('accion') === $accion)>{{ ucfirst(str_replace('_', ' ', $accion)) }}</option>@endforeach
    </select>
    <select name="modulo">
        <option value="">Todos los módulos</option>
        @foreach($modulos as $modulo)<option value="{{ $modulo }}" @selected(request('modulo') === $modulo)>{{ ucfirst(str_replace('_', ' ', $modulo)) }}</option>@endforeach
    </select>
    <select name="usuario_id">
        <option value="">Todos los usuarios</option>
        @foreach($usuarios as $usuario)<option value="{{ $usuario->id }}" @selected((string) request('usuario_id') === (string) $usuario->id)>{{ $usuario->name }} ({{ $usuario->username }})</option>@endforeach
    </select>
    <input type="date" name="desde" value="{{ request('desde') }}">
    <input type="date" name="hasta" value="{{ request('hasta') }}">
    <button class="btn secondary">Filtrar</button>
    <a class="btn secondary" href="{{ route('seguridad.auditoria') }}">Limpiar</a>
</form>

<div class="card">
    <table>
        <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Módulo</th><th>Registro</th><th>Descripción</th><th>Detalle</th></tr></thead>
        <tbody>
        @forelse($eventos as $evento)
            <tr>
                <td>{{ $evento->created_at?->format('d/m/Y H:i:s') ?: '-' }}</td>
                <td>{{ $evento->usuario?->name ?: $evento->usuario?->username ?: 'Sistema / usuario eliminado' }}@if($evento->usuario?->username)<br><small>{{ $evento->usuario->username }}</small>@endif</td>
                <td><strong>{{ ucfirst(str_replace('_', ' ', $evento->accion)) }}</strong></td>
                <td>{{ ucfirst(str_replace('_', ' ', $evento->modulo)) }}</td>
                <td>{{ $evento->auditable_type ? class_basename($evento->auditable_type).' #'.$evento->auditable_id : '-' }}</td>
                <td>{{ $evento->descripcion ?: '-' }}</td>
                <td>
                    @if($evento->valores_anteriores || $evento->valores_nuevos)
                        <details><summary>Ver cambios</summary>
                            @if($evento->valores_anteriores)<strong>Antes:</strong><pre style="white-space:pre-wrap;max-width:300px;">{{ json_encode($evento->valores_anteriores, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
                            @if($evento->valores_nuevos)<strong>Después:</strong><pre style="white-space:pre-wrap;max-width:300px;">{{ json_encode($evento->valores_nuevos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
                        </details>
                    @else - @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7">No hay eventos para los filtros seleccionados.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pagination">{{ $eventos->links() }}</div>
</div>
@endsection
