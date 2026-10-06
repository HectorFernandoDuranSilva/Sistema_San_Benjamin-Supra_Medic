<table class="history"><thead><tr><th>Fecha / Documento</th><th>Empresa / Cliente</th><th>Productos y lotes</th><th>Total</th><th>Estado de pago</th><th>Último pago</th><th>Estado del documento</th></tr></thead><tbody>
@forelse($ventas as $venta)
@php($paid = $venta->cobros->where('estado', 'registrado')->sum('importe'))
<tr><td>{{ $venta->fecha->format('d/m/Y') }}<br>{{ $venta->numero_venta }}<br>{{ $venta->tipo_documento === 'nota_entrega' ? 'Nota de entrega' : 'Factura' }} {{ $venta->factura }}</td>
<td>{{ $venta->nombre_comercial }}<br>{{ $venta->cliente?->nombre_mostrable ?: 'Sin cliente' }}</td>
<td>{{ $venta->detalles->count() }} ítems</td>
<td>Bs {{ number_format($venta->total, 2) }}</td><td>@if($venta->estado === 'anulada')No aplica @elseif($paid >= $venta->total)Pagada @elseif($paid > 0)Parcial @else Pendiente @endif</td><td>{{ $venta->cobros->where('estado', 'registrado')->sortByDesc('fecha')->first()?->fecha?->format('d/m/Y') ?? '-' }}</td><td>{{ ucfirst($venta->estado) }}</td></tr>
@foreach($venta->detalles as $detalle)
<tr><td>{{ $detalle->codigo_producto }}</td><td colspan="2">{{ $detalle->nombre_producto }}</td><td colspan="2">{{ number_format($detalle->cantidad, 2) }} × Bs {{ number_format($detalle->precio_unitario, 2) }}</td><td colspan="2">Importe: Bs {{ number_format($detalle->total, 2) }}</td></tr>
@foreach($detalle->lotes as $allocation)
<tr><td></td><td colspan="2">Lote: {{ $allocation->lote?->codigo_lote ?: '-' }}</td><td colspan="2">Cantidad: {{ number_format($allocation->cantidad, 2) }}</td><td colspan="2">Vence: {{ $allocation->fecha_vencimiento?->format('d/m/Y') ?: '-' }}</td></tr>
@endforeach
@endforeach
@empty<tr><td colspan="7">Sin operaciones para los filtros seleccionados.</td></tr>@endforelse
</tbody></table>
