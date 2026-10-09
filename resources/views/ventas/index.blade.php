@extends('layouts.app')

@section('content')
<div class="top"><h1>Ventas</h1><div class="actions"><a class="btn" href="{{ route('ventas.create') }}">Nueva venta</a></div></div>
<form class="card" method="get" style="margin-bottom:12px;">
    <div class="row">
        <div class="field"><label>Buscar</label><input name="buscar" value="{{ $filters['buscar'] ?? '' }}" placeholder="N.º de venta, factura, NIT o cliente"></div>
        <div class="field"><label>Nombre comercial</label><select name="nombre_comercial"><option value="">Ambos</option>@foreach(['San Benjamin', 'Supra Medic'] as $nombre)<option value="{{ $nombre }}" @selected(($filters['nombre_comercial'] ?? '') === $nombre)>{{ $nombre }}</option>@endforeach</select></div>
        <div class="field"><label>Año</label><input type="number" name="anio" min="2000" max="2100" value="{{ $filters['anio'] ?? '' }}" placeholder="2026"></div>
    </div>
    <button class="btn secondary">Buscar facturas</button>
</form>
@if($notasCoincidentes->isNotEmpty())
<div class="alert-item" style="margin-bottom:12px;">
    <strong><span>El resultado está registrado como nota de entrega</span><span>{{ $notasCoincidentes->count() }}</span></strong>
    @foreach($notasCoincidentes as $nota)
        <small><a href="{{ route('notas.show', $nota) }}">{{ $nota->numero_venta }}</a> · {{ $nota->cliente?->nombre_mostrable ?? 'Sin cliente' }} · {{ $nota->fecha->format('d/m/Y') }} · Bs {{ number_format($nota->total, 2) }}</small>
    @endforeach
</div>
@endif
<div class="card"><table><tr><th>No.</th><th>Orden</th><th>Documento</th><th>Fecha</th><th>Factura</th><th>NIT</th><th>Cliente</th><th>Total</th><th>Documento</th><th>Pago</th><th>Usuario</th><th></th></tr>
@foreach($ventas as $venta)
@php($cobrado = (float) ($venta->cobrado ?? 0))
<tr><td>{{ $venta->numero_venta }}</td><td>{{ $venta->nombre_comercial }}</td><td>{{ $venta->tipo_documento === 'nota_entrega' ? 'Nota de entrega' : 'Factura' }}</td><td>{{ $venta->fecha->format('d/m/Y') }}</td><td>{{ $venta->factura }}</td><td>{{ $venta->nit_cliente }}</td><td>{{ $venta->cliente?->nombre_mostrable ?? 'Sin cliente' }}</td><td>Bs {{ number_format($venta->total, 2) }}</td><td>{{ ucfirst($venta->estado) }}</td><td>@if($venta->estado === 'anulada')No aplica @elseif($cobrado >= (float) $venta->total)Pagada<br><small>{{ \Illuminate\Support\Carbon::parse($venta->fecha_ultimo_cobro)->format('d/m/Y') }}</small> @elseif($cobrado > 0)Parcial<br><small>Saldo: Bs {{ number_format($venta->total - $cobrado, 2) }}</small> @else Pendiente @endif</td><td>{{ $venta->usuario?->name }}</td><td><div class="actions" style="gap:8px;"><a href="{{ route('ventas.show', $venta) }}">Ver</a><a href="{{ route('ventas.recibo', $venta) }}">Vista previa</a><a href="{{ route('ventas.historial.edit', $venta) }}">Editar</a></div></td></tr>
@endforeach
</table>{{ $ventas->links() }}</div>
@endsection
