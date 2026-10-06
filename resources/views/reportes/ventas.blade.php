@extends('layouts.app')

@section('content')
<div class="top"><h1>Reporte de ventas</h1><div class="actions"><a class="btn secondary" href="{{ route('reportes.guardados') }}">Reportes guardados</a><a class="btn secondary" href="{{ route('reportes.documentos') }}">Notas/Facturas</a><a class="btn secondary" href="{{ route('reportes.inventario') }}">Inventario</a><a class="btn secondary" href="{{ route('reportes.compras') }}">Compras</a></div></div>
<form class="actions" method="get" style="margin-bottom:12px;"><input type="date" name="desde" value="{{ request('desde') }}"><input type="date" name="hasta" value="{{ request('hasta') }}"><select name="estado"><option value="">Estado</option><option value="registrada" @selected(request('estado')==='registrada')>Registrada</option><option value="anulada" @selected(request('estado')==='anulada')>Anulada</option></select><button class="btn secondary">Filtrar</button></form>
<form class="actions" method="post" action="{{ route('reportes.guardados.store') }}" style="margin-bottom:12px;">@csrf<input name="titulo" value="Reporte de ventas {{ now()->format('d/m/Y') }}"><input type="hidden" name="tipo" value="ventas"><input type="hidden" name="ruta" value="{{ request()->getRequestUri() }}"><input name="observacion" placeholder="Observación"><button class="btn secondary">Guardar reporte actual</button></form>
<div class="card"><table><tr><th>Fecha</th><th>No.</th><th>Cliente</th><th>Total</th><th>Estado</th><th>Usuario</th></tr>@foreach($ventas as $v)<tr><td>{{ $v->fecha->format('d/m/Y') }}</td><td>{{ $v->numero_venta }}</td><td>{{ $v->cliente?->nombre }}</td><td>Bs {{ number_format($v->total,2) }}</td><td>{{ $v->estado }}</td><td>{{ $v->usuario?->name }}</td></tr>@endforeach</table>{{ $ventas->links() }}</div>
@endsection

