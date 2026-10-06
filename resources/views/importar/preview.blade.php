@extends('layouts.app')

@section('content')
<div class="top"><h1>Vista previa de importacion</h1><form method="post" action="{{ route('importar.confirm') }}">@csrf<button class="btn">Confirmar importacion</button></form></div>
<div class="grid cols-4"><div class="card"><div class="muted">Productos</div><div class="stat">{{ $resumen['productos'] }}</div></div><div class="card"><div class="muted">Clientes</div><div class="stat">{{ $resumen['clientes'] }}</div></div><div class="card"><div class="muted">Proveedores</div><div class="stat">{{ $resumen['proveedores'] }}</div></div><div class="card"><div class="muted">Alertas</div><div class="stat">{{ $resumen['errores'] }}</div></div></div>
@if($errores)<div class="errors" style="margin-top:16px;">@foreach($errores as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="card" style="margin-top:16px;"><h2>Primeros productos</h2><table><tr><th>Fila</th><th>Codigo</th><th>Nombre</th><th>Categoria</th><th>Stock</th><th>Ingreso USD</th><th>Cambio</th><th>%</th><th>Venta Bs</th></tr>@foreach(array_slice($productos, 0, 50) as $p)<tr><td>{{ $p['fila'] }}</td><td>{{ $p['codigo'] }}</td><td>{{ $p['nombre'] }}</td><td>{{ $p['categoria'] }}</td><td>{{ $p['stock_actual'] }}</td><td>{{ number_format($p['precio_ingreso_usd'], 2) }}</td><td>{{ number_format($p['tipo_cambio'], 4) }}</td><td>{{ number_format($p['porcentaje_utilidad'], 2) }}%</td><td>{{ number_format($p['precio_venta'], 2) }}</td></tr>@endforeach</table></div>
@endsection
