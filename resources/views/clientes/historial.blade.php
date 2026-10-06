@extends('layouts.app')

@section('content')
<style>
    .client-history { max-width: 1560px; margin: 0 auto; }
    .client-history .history-toolbar { align-items: flex-start; margin-bottom: 14px; }
    .client-history .history-toolbar h1 { margin-bottom: 6px; }
    .client-history .history-toolbar p { margin: 0; max-width: 760px; line-height: 1.45; }
    .client-history .history-filter-band { display: grid; grid-template-columns: minmax(260px, 1.45fr) repeat(3, minmax(150px, 1fr)); gap: 12px; align-items: end; padding: 16px; margin-bottom: 20px; border: 1px solid var(--line); border-radius: 8px; background: var(--panel); box-shadow: var(--shadow); }
    .client-history .history-filter-band .field { margin: 0; }
    .client-history .history-filter-band .date-range { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .client-history .history-filter-band .filter-action { align-self: end; }
    .client-history .history-filter-band .filter-action .btn { width: 100%; min-height: 43px; }
    .client-history .history-summary-heading { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; margin: 0 0 10px; }
    .client-history .history-summary-heading h2, .client-history .history-documents-heading h2 { margin: 0; font-size: 18px; }
    .client-history .history-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; margin-bottom: 24px; }
    .client-history .history-summary-card { min-height: 104px; padding: 14px; border: 1px solid var(--line); border-left: 4px solid var(--primary); border-radius: 8px; background: var(--panel); box-shadow: var(--shadow); }
    .client-history .history-summary-card.supra { border-left-color: #28bfae; }
    .client-history .history-summary-card .summary-label { display: block; color: var(--muted); font-size: 12px; font-weight: 700; }
    .client-history .history-summary-card strong { display: block; margin-top: 10px; font-size: 21px; color: inherit; }
    .client-history .history-documents-heading { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; margin-bottom: 10px; }
    .client-history .history-documents-heading span { color: var(--muted); font-size: 13px; }
    .client-history .history-table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 8px; background: var(--panel); box-shadow: var(--shadow); }
    .client-history .history-table { min-width: 1120px; }
    .client-history .history-table th { padding: 12px 14px; white-space: nowrap; }
    .client-history .history-table td { padding: 13px 14px; vertical-align: top; }
    .client-history .history-table tbody tr:hover td { background: rgba(103,190,217,.07); }
    .client-history .history-document-number { display: block; margin-top: 3px; font-size: 12px; font-weight: 800; }
    .client-history .history-document-meta, .client-history .history-customer { display: block; margin-top: 3px; color: var(--muted); font-size: 12px; line-height: 1.35; }
    .client-history .history-customer { color: inherit; font-weight: 700; }
    .client-history .history-total { font-weight: 800; white-space: nowrap; }
    .client-history .history-status { display: inline-flex; padding: 4px 7px; border-radius: 999px; background: var(--soft); color: var(--primary); font-size: 12px; font-weight: 800; white-space: nowrap; }
    .client-history .history-status.partial { color: #a16207; background: rgba(250,204,21,.16); }
    .client-history .history-status.pending { color: #b45309; background: rgba(251,146,60,.15); }
    .client-history .history-status.cancelled { color: #be123c; background: rgba(244,63,94,.14); }
    .client-history .history-payment-action { display: block; width: max-content; margin-top: 7px; color: var(--primary); font-size: 12px; font-weight: 800; }
    .client-history .history-payment-action:hover { text-decoration: underline; }
    .client-history .history-items { min-width: 205px; }
    .client-history .history-items summary { cursor: pointer; color: var(--primary); font-size: 12px; font-weight: 800; }
    .client-history .history-items-list { display: grid; gap: 7px; margin-top: 9px; padding-top: 8px; border-top: 1px solid var(--line); }
    .client-history .history-item { display: grid; gap: 3px; font-size: 12px; line-height: 1.3; }
    .client-history .history-item strong { font-size: 12px; }
    .client-history .history-item span, .client-history .history-lot { color: var(--muted); }
    .client-history .history-lot { padding-left: 8px; border-left: 2px solid rgba(103,190,217,.38); }
    .client-history .empty-history { padding: 34px 16px; color: var(--muted); text-align: center; }
    @media (max-width: 1080px) { .client-history .history-filter-band { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 640px) { .client-history .history-filter-band, .client-history .history-filter-band .date-range { grid-template-columns: 1fr; } .client-history .history-summary-grid { grid-template-columns: 1fr; } }
</style>

<div class="client-history">
    <div class="top history-toolbar">
        <div>
            <h1>Historial de ventas por cliente</h1>
            <p class="muted">Consulte las ventas registradas por cliente, empresa, año y documento. Los movimientos de cobro se revisan desde Kardex.</p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('reportes.kardex') }}">Kardex</a>
            <a class="btn secondary" href="{{ route('clientes.historial.pdf', request()->query()) }}" target="_blank">PDF / Imprimir</a>
        </div>
    </div>

    <form method="get" class="history-filter-band">
        <div class="field">
            <label>Cliente</label>
            <select name="cliente_id">
                <option value="">Todos los clientes</option>
                @foreach($clientes as $cliente)
                    <option value="{{ $cliente->id }}" @selected(request('cliente_id') == $cliente->id)>{{ $cliente->nombre_mostrable }}{{ $cliente->documento ? ' - '.$cliente->documento : '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Empresa</label>
            <select name="nombre_comercial">
                <option value="">Ambas empresas</option>
                @foreach(['San Benjamin', 'Supra Medic'] as $brand)
                    <option value="{{ $brand }}" @selected(request('nombre_comercial') === $brand)>{{ $brand }}</option>
                @endforeach
            </select>
        </div>
        <div class="field"><label>Año</label><input type="number" name="anio" min="2000" max="2100" value="{{ request('anio') }}" placeholder="2026"></div>
        <div class="field">
            <label>Documento</label>
            <select name="tipo_documento">
                <option value="">Facturas y notas</option>
                <option value="factura" @selected(request('tipo_documento') === 'factura')>Facturas</option>
                <option value="nota_entrega" @selected(request('tipo_documento') === 'nota_entrega')>Notas de entrega</option>
            </select>
        </div>
        <div class="date-range">
            <div class="field"><label>Desde</label><input type="date" name="desde" value="{{ request('desde') }}"></div>
            <div class="field"><label>Hasta</label><input type="date" name="hasta" value="{{ request('hasta') }}"></div>
        </div>
        <div class="filter-action"><button class="btn secondary">Filtrar</button></div>
    </form>

    <section>
        <div class="history-summary-heading"><h2>Resumen de ventas</h2><span class="muted">Importes sin documentos anulados</span></div>
        <div class="history-summary-grid">
            @forelse($totales as $total)
                <article @class(['history-summary-card', 'supra' => str_contains(strtolower($total->nombre_comercial), 'supra')])>
                    <span class="summary-label">{{ $total->nombre_comercial }} · {{ $total->tipo_documento === 'nota_entrega' ? 'Notas de entrega' : 'Facturas' }}</span>
                    <strong>Bs {{ number_format($total->importe, 2) }}</strong>
                </article>
            @empty
                <article class="history-summary-card"><span class="summary-label">Sin resultados para el filtro aplicado</span><strong>Bs 0.00</strong></article>
            @endforelse
        </div>
    </section>

    <section>
        <div class="history-documents-heading"><h2>Documentos registrados</h2><span>{{ $ventas->total() }} {{ $ventas->total() === 1 ? 'documento' : 'documentos' }}</span></div>
        <div class="history-table-wrap">
            <table class="history-table">
                <thead><tr><th>Documento</th><th>Empresa y cliente</th><th>Productos y lotes</th><th>Total</th><th>Pago</th><th>Último pago</th><th>Estado</th></tr></thead>
                <tbody>
                    @forelse($ventas as $venta)
                        @php
                            $pagado = (float) $venta->cobros->where('estado', 'registrado')->sum('importe');
                            $estadoPago = $venta->estado === 'anulada' ? 'No aplica' : ($pagado >= (float) $venta->total ? 'Pagada' : ($pagado > 0 ? 'Parcial' : 'Pendiente'));
                            $clasePago = $venta->estado === 'anulada' ? 'cancelled' : strtolower($estadoPago);
                        @endphp
                        <tr>
                            <td><strong>{{ $venta->fecha->format('d/m/Y') }}</strong><span class="history-document-number">{{ $venta->numero_venta }}</span><span class="history-document-meta">{{ $venta->tipo_documento === 'nota_entrega' ? 'Nota de entrega' : 'Factura' }}{{ $venta->factura ? ' · '.$venta->factura : '' }}</span></td>
                            <td><strong>{{ $venta->nombre_comercial }}</strong><span class="history-customer">{{ $venta->cliente?->nombre_mostrable ?: 'Sin cliente' }}</span></td>
                            <td class="history-items">
                                <details>
                                    <summary>{{ $venta->detalles->count() }} {{ $venta->detalles->count() === 1 ? 'producto' : 'productos' }}</summary>
                                    <div class="history-items-list">
                                        @foreach($venta->detalles as $detalle)
                                            <div class="history-item">
                                                <strong>{{ $detalle->codigo_producto }} · {{ $detalle->nombre_producto }}</strong>
                                                <span>{{ number_format($detalle->cantidad, 2) }} × Bs {{ number_format($detalle->precio_unitario, 2) }} = Bs {{ number_format($detalle->total, 2) }}</span>
                                                @foreach($detalle->lotes as $allocation)
                                                    <span class="history-lot">Lote {{ $allocation->lote?->codigo_lote ?: '-' }} · vence {{ $allocation->fecha_vencimiento?->format('d/m/Y') ?: 'sin fecha' }}</span>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            </td>
                            <td class="history-total">Bs {{ number_format($venta->total, 2) }}</td>
                            <td>
                                <span class="history-status {{ $clasePago }}">{{ $estadoPago }}</span>
                                @if($venta->estado !== 'anulada' && $pagado < (float) $venta->total && auth()->user()->hasPermission('ventas'))
                                    <a class="history-payment-action" href="{{ route($venta->tipo_documento === 'nota_entrega' ? 'notas.show' : 'ventas.show', $venta) }}#cobros">Registrar cobro</a>
                                @endif
                            </td>
                            <td>{{ $venta->cobros->where('estado', 'registrado')->sortByDesc('fecha')->first()?->fecha?->format('d/m/Y') ?? '-' }}</td>
                            <td><span class="history-status {{ $venta->estado === 'anulada' ? 'cancelled' : '' }}">{{ ucfirst($venta->estado) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-history">No hay documentos para los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $ventas->links() }}</div>
    </section>
</div>
@endsection
