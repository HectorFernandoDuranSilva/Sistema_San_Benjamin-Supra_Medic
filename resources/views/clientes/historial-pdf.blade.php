<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
@page { margin: 26pt; } body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color:#172033; }
h1 { font-size:18pt; } h2 { font-size:12pt; } table { width:100%; border-collapse:collapse; }
th,td { border:.5pt solid #becbd3; padding:5pt; vertical-align:top; } th { background:#eaf2f6; text-align:left; }
thead { display:table-header-group; } .brand { margin-top:16pt; } .brand img { height:45pt; width:auto; vertical-align:middle; margin-right:18pt; }
.history td:nth-child(3) { width:32%; } .history td div { margin-bottom:6pt; } .muted { color:#526475; }
</style></head><body>
<h1>Reporte de ingresos por cliente</h1>
<p class="muted">Ingresos calculados por ventas realizadas según la fecha del documento. Período: {{ $filtros['desde'] ?? 'Inicio' }} a {{ $filtros['hasta'] ?? 'Actualidad' }}. Generado: {{ now()->format('d/m/Y H:i') }}.</p>
@foreach($ventas->groupBy('nombre_comercial') as $brand => $operations)
<h2 class="brand"><img src="{{ public_path('assets/logos/' . (str_contains(strtolower($brand), 'supra') ? 'supra-medic' : 'san-benjamin') . '.jpeg') }}">{{ $brand }}</h2>
<p>Facturas: Bs {{ number_format($operations->where('estado', '!=', 'anulada')->where('tipo_documento', 'factura')->sum('total'), 2) }} | Notas: Bs {{ number_format($operations->where('estado', '!=', 'anulada')->where('tipo_documento', 'nota_entrega')->sum('total'), 2) }}</p>
@include('clientes.partials.history-rows', ['ventas' => $operations])
@endforeach
</body></html>
