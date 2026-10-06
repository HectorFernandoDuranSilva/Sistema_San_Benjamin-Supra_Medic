@extends('layouts.app')

@section('content')
<div class="top">
    <h1>Reportes guardados</h1>
    <div class="actions">
        <a class="btn secondary" href="{{ route('reportes.ventas') }}">Ventas</a>
        <a class="btn secondary" href="{{ route('reportes.inventario') }}">Inventario</a>
        <a class="btn secondary" href="{{ route('reportes.compras') }}">Compras</a>
        <a class="btn secondary" href="{{ route('reportes.kardex') }}">Kardex</a>
        <a class="btn secondary" href="{{ route('reportes.balance-mensual') }}">Balance mensual</a>
    </div>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Titulo</th>
                <th>Tipo</th>
                <th>Ruta</th>
                <th>Observación</th>
                <th>Usuario</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportes as $reporte)
                <tr>
                    <td>
                        <form id="reporte-edit-{{ $reporte->id }}" method="post" action="{{ route('reportes.guardados.update', $reporte) }}">
                            @csrf
                            @method('put')
                            <input name="titulo" value="{{ $reporte->titulo }}" required>
                        </form>
                    </td>
                    <td>
                        <select form="reporte-edit-{{ $reporte->id }}" name="tipo">
                            @foreach(['ventas', 'compras', 'inventario', 'kardex', 'stock_bajo', 'documentos', 'balance_mensual'] as $tipo)
                                <option value="{{ $tipo }}" @selected($reporte->tipo === $tipo)>{{ ucfirst(str_replace('_', ' ', $tipo)) }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input form="reporte-edit-{{ $reporte->id }}" name="ruta" value="{{ $reporte->ruta }}" required></td>
                    <td><input form="reporte-edit-{{ $reporte->id }}" name="observacion" value="{{ $reporte->observacion }}"></td>
                    <td>{{ $reporte->usuario?->name }}</td>
                    <td>
                        <div class="actions" style="gap:8px;">
                            <a class="btn secondary" href="{{ $reporte->ruta }}">Abrir</a>
                            <button form="reporte-edit-{{ $reporte->id }}" class="btn secondary">Guardar</button>
                            <details class="force-delete">
                                <summary>Eliminar</summary>
                                <form method="post" action="{{ route('reportes.guardados.destroy', $reporte) }}" data-glass-confirm="Esta acción eliminara el reporte guardado. ¿Desea continuar?">
                                    @csrf
                                    @method('delete')
                                    <input name="totp_code" inputmode="numeric" maxlength="32" placeholder="Código Authenticator" required>
                                    <button class="btn force-danger">Eliminar</button>
                                </form>
                            </details>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No hay reportes guardados todavia.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="pagination">{{ $reportes->links() }}</div>
</div>
@endsection

