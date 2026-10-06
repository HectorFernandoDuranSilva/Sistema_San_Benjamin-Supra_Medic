@extends('layouts.app')

@section('content')
<div class="top"><h1>Movimientos de inventario</h1></div>
<form class="card" method="post" action="{{ route('inventario.ajustar') }}" style="margin-bottom:16px;">
    @csrf
    <h2>Ajuste manual</h2>
    <div class="row"><div class="field"><label>Producto</label><select name="producto_id">@foreach($productos as $p)<option value="{{ $p->id }}">{{ $p->codigo }} - {{ $p->nombre }} | {{ $p->marca ?: 'Sin marca' }} | {{ $p->procedencia ?: 'Sin procedencia' }}</option>@endforeach</select></div><div class="field"><label>Tipo</label><select name="tipo_movimiento"><option value="ajuste_positivo">Ajuste positivo</option><option value="ajuste_negativo">Ajuste negativo</option></select></div><div class="field"><label>Cantidad</label><input type="number" step="0.01" min="0.01" name="cantidad"></div><div class="field"><label>Observacion</label><input name="observacion"></div></div>
    <button class="btn">Registrar ajuste</button>
</form>
<div class="card"><table><tr><th>Fecha</th><th>Producto</th><th>Marca</th><th>Procedencia</th><th>Tipo</th><th>Cantidad</th><th>Anterior</th><th>Nuevo</th><th>Usuario</th><th>Referencia</th></tr>
@foreach($movimientos as $m)
    <tr><td>{{ $m->fecha_movimiento->format('d/m/Y H:i') }}</td><td>{{ $m->producto?->codigo }} {{ $m->producto?->nombre }}</td><td>{{ $m->producto?->marca ?: '-' }}</td><td>{{ $m->producto?->procedencia ?: '-' }}</td><td>{{ $m->tipo_movimiento }}</td><td>{{ $m->cantidad }}</td><td>{{ $m->stock_anterior }}</td><td>{{ $m->stock_nuevo }}</td><td>{{ $m->usuario?->name }}</td><td>{{ $m->referencia_tipo }} #{{ $m->referencia_id }}</td></tr>
@endforeach
</table><div class="pagination">{{ $movimientos->links() }}</div></div>
@endsection
