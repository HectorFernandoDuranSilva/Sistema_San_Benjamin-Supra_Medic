@extends('layouts.app')

@section('content')
<div class="top"><h1>Compras</h1><a class="btn" href="{{ route('compras.create') }}">Nueva compra</a></div>
<form class="card" method="get" style="margin-bottom:12px;">
    <div class="row">
        <div class="field"><label>Buscar</label><input name="buscar" value="{{ $filters['buscar'] ?? '' }}" placeholder="N.º de compra, factura, NIT o proveedor"></div>
        <div class="field"><label>Documento</label><select name="tipo_documento"><option value="">Todos</option><option value="factura" @selected(($filters['tipo_documento'] ?? '') === 'factura')>Factura</option><option value="nota_entrega" @selected(($filters['tipo_documento'] ?? '') === 'nota_entrega')>Nota de entrega</option></select></div>
        <div class="field"><label>Nombre comercial</label><select name="nombre_comercial"><option value="">Ambos</option>@foreach(['San Benjamin', 'Supra Medic'] as $nombre)<option value="{{ $nombre }}" @selected(($filters['nombre_comercial'] ?? '') === $nombre)>{{ $nombre }}</option>@endforeach</select></div>
        <div class="field"><label>Año</label><input type="number" name="anio" min="2000" max="2100" value="{{ $filters['anio'] ?? '' }}" placeholder="2026"></div>
    </div>
    <button class="btn secondary">Buscar documentos</button>
</form>
<div class="card"><table><tr><th>No.</th><th>Fecha</th><th>Documento</th><th>Factura</th><th>Proveedor</th><th>Total</th><th>Documento</th><th>Pago</th><th>Usuario</th><th></th></tr>
@foreach($compras as $compra)
@php($pagado = (float) ($compra->pagado ?? 0))
<tr><td>{{ $compra->numero_compra }}</td><td>{{ $compra->fecha->format('d/m/Y') }}</td><td>{{ $compra->tipo_documento === 'nota_entrega' ? 'Nota de entrega' : 'Factura' }}</td><td>{{ $compra->factura ?: '-' }}</td><td>{{ $compra->proveedor?->nombre ?? 'Sin proveedor' }}</td><td>Bs {{ number_format($compra->total, 2) }}</td><td>{{ ucfirst($compra->estado) }}</td><td>@if($compra->estado !== 'registrada')No aplica @elseif($pagado >= (float) $compra->total)Pagada<br><small>{{ \Illuminate\Support\Carbon::parse($compra->fecha_ultimo_pago)->format('d/m/Y') }}</small> @elseif($pagado > 0)Parcial<br><small>Saldo: Bs {{ number_format($compra->total - $pagado, 2) }}</small> @else Pendiente @endif</td><td>{{ $compra->usuario?->name }}</td><td><div class="actions" style="gap:8px;"><a href="{{ route('compras.show', $compra) }}">Ver</a><a href="{{ route('compras.recibo', $compra) }}">Vista previa</a><a href="{{ route('compras.historial.edit', $compra) }}">Editar</a></div></td></tr>
@endforeach
</table>{{ $compras->links() }}</div>
@endsection
