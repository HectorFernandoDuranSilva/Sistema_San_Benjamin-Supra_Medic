@extends('layouts.app')

@section('content')
<div class="top"><h1>Reporte de compras</h1><div class="actions"><a class="btn secondary" href="{{ route('reportes.documentos') }}">Notas/Facturas</a><a class="btn secondary" href="{{ route('reportes.guardados') }}">Reportes guardados</a></div></div>
<form class="actions" method="get" style="margin-bottom:12px;"><input type="date" name="desde" value="{{ request('desde') }}"><input type="date" name="hasta" value="{{ request('hasta') }}"><button class="btn secondary">Filtrar</button></form>
<form class="actions" method="post" action="{{ route('reportes.guardados.store') }}" style="margin-bottom:12px;">@csrf<input name="titulo" value="Reporte de compras {{ now()->format('d/m/Y') }}"><input type="hidden" name="tipo" value="compras"><input type="hidden" name="ruta" value="{{ request()->getRequestUri() }}"><input name="observacion" placeholder="Observación"><button class="btn secondary">Guardar reporte actual</button></form>
<div class="card"><table><tr><th>Fecha</th><th>No.</th><th>Proveedor</th><th>Total</th><th>Estado</th><th>Usuario</th></tr>@foreach($compras as $c)<tr><td>{{ $c->fecha->format('d/m/Y') }}</td><td>{{ $c->numero_compra }}</td><td>{{ $c->proveedor?->nombre }}</td><td>Bs {{ number_format($c->total,2) }}</td><td>{{ $c->estado }}</td><td>{{ $c->usuario?->name }}</td></tr>@endforeach</table>{{ $compras->links() }}</div>
@endsection

