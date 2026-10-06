@extends('layouts.app')

@section('content')
<div class="top"><h1>Reporte de inventario</h1><div class="actions"><a class="btn secondary" href="{{ route('reportes.guardados') }}">Reportes guardados</a><a class="btn secondary" href="{{ route('reportes.stock-bajo.pdf') }}">PDF stock bajo</a><a class="btn secondary" href="{{ route('reportes.kardex') }}">Kardex</a></div></div>
<form class="actions" method="get" style="margin-bottom:12px;"><input type="date" name="desde" value="{{ request('desde') }}"><input type="date" name="hasta" value="{{ request('hasta') }}"><select name="producto_id"><option value="">Producto</option>@foreach($productos as $p)<option value="{{ $p->id }}" @selected(request('producto_id')==$p->id)>{{ $p->codigo }} {{ $p->nombre }}</option>@endforeach</select><button class="btn secondary">Filtrar</button></form>
<form class="actions" method="post" action="{{ route('reportes.guardados.store') }}" style="margin-bottom:12px;">@csrf<input name="titulo" value="Reporte de inventario {{ now()->format('d/m/Y') }}"><input type="hidden" name="tipo" value="inventario"><input type="hidden" name="ruta" value="{{ request()->getRequestUri() }}"><input name="observacion" placeholder="Observación"><button class="btn secondary">Guardar reporte actual</button></form>
<div class="card"><table><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Stock nuevo</th><th>Usuario</th></tr>@foreach($movimientos as $m)<tr><td>{{ $m->fecha_movimiento->format('d/m/Y H:i') }}</td><td>{{ $m->producto?->codigo }} {{ $m->producto?->nombre }}</td><td>{{ $m->tipo_movimiento }}</td><td>{{ $m->cantidad }}</td><td>{{ $m->stock_nuevo }}</td><td>{{ $m->usuario?->name }}</td></tr>@endforeach</table>{{ $movimientos->links() }}</div>
@endsection

