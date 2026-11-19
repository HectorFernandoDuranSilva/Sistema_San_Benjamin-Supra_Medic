@extends('layouts.app')

@section('content')
<div class="top">
    <h1>Notas y facturas</h1>
    <div class="actions">
        @if(auth()->user()->hasPermission('ventas'))
            <a class="btn" href="{{ route('notas.create') }}">Crear nota de entrega</a>
        @endif
        @if(auth()->user()->hasPermission('notas_facturas'))
            <a class="btn secondary" href="{{ route('notas-facturas.cobros') }}">Cuentas por cobrar</a>
            <a class="btn secondary" href="{{ route('reportes.gastos-operativos') }}">Gastos operativos</a>
        @endif
        <a class="btn secondary" href="{{ route('reportes.documentos.pdf', request()->query()) }}">Descargar PDF</a>
        <a class="btn secondary" href="{{ route('reportes.documentos.excel', request()->query()) }}">Descargar Excel</a>
        <a class="btn secondary" href="{{ route('reportes.ventas') }}">Ventas</a>
        <a class="btn secondary" href="{{ route('reportes.compras') }}">Compras</a>
    </div>
</div>

<form class="card" method="get" style="margin-bottom:12px;">
    <div class="row">
        <div class="field"><label>Buscar</label><input name="buscar" value="{{ $filtros['buscar'] }}" placeholder="N.º, factura, nota, NIT, cliente o proveedor"></div>
        <div class="field"><label>Desde</label><input type="date" name="desde" value="{{ $filtros['desde'] }}"></div>
        <div class="field"><label>Hasta</label><input type="date" name="hasta" value="{{ $filtros['hasta'] }}"></div>
        <div class="field">
            <label>Documento</label>
            <select name="tipo_documento">
                <option value="">Todos</option>
                <option value="factura" @selected($filtros['tipo_documento'] === 'factura')>Factura</option>
                <option value="nota_entrega" @selected($filtros['tipo_documento'] === 'nota_entrega')>Nota de entrega</option>
            </select>
        </div>
        <div class="field">
            <label>Nombre comercial</label>
            <select name="nombre_comercial">
                <option value="">Todos</option>
                <option value="San Benjamin" @selected($filtros['nombre_comercial'] === 'San Benjamin')>San Benjamin</option>
                <option value="Supra Medic" @selected($filtros['nombre_comercial'] === 'Supra Medic')>Supra Medic</option>
            </select>
        </div>
    </div>
    <div class="actions"><button class="btn secondary">Filtrar</button></div>
</form>

<form class="actions" method="post" action="{{ route('reportes.guardados.store') }}" style="margin-bottom:12px;">
    @csrf
    <input name="titulo" value="Notas y facturas {{ now()->format('d/m/Y') }}">
    <input type="hidden" name="tipo" value="documentos">
    <input type="hidden" name="ruta" value="{{ request()->getRequestUri() }}">
    <input name="observacion" placeholder="Observación">
    <button class="btn secondary">Guardar reporte actual</button>
</form>

<div class="grid cols-2">
    <div class="card">
        <h2>Resumen de documentos emitidos</h2>
        <table>
            <tr><th>Nombre comercial</th><th>Documento</th><th>Cantidad</th><th>Total</th></tr>
            @forelse($ventasResumen as $item)
                <tr><td>{{ $item['nombre_comercial'] }}</td><td>{{ $item['tipo_documento'] === 'nota_entrega' ? 'Nota de entrega' : 'Factura' }}</td><td>{{ $item['cantidad'] }}</td><td>Bs {{ number_format($item['total'], 2) }}</td></tr>
            @empty
                <tr><td colspan="4">Sin ventas en el rango seleccionado.</td></tr>
            @endforelse
        </table>
    </div>
    <div class="card">
        <h2>Resumen de compras</h2>
        <table>
            <tr><th>Nombre comercial</th><th>Documento</th><th>Cantidad</th><th>Total</th></tr>
            @forelse($comprasResumen as $item)
                <tr><td>{{ $item['nombre_comercial'] }}</td><td>{{ $item['tipo_documento'] === 'nota_entrega' ? 'Nota de entrega' : 'Factura' }}</td><td>{{ $item['cantidad'] }}</td><td>Bs {{ number_format($item['total'], 2) }}</td></tr>
            @empty
                <tr><td colspan="4">Sin compras en el rango seleccionado.</td></tr>
            @endforelse
        </table>
    </div>
</div>

<section id="notas-entrega" class="card" style="margin-top:24px;">
    <div class="top">
        <div><h2>Notas de entrega</h2><p class="muted" style="margin:4px 0 0;">Total de notas: <strong>Bs {{ number_format($totales['notas_entrega'], 2) }}</strong>. Este importe no se integra al balance mensual.</p></div>
        <a class="btn secondary" href="{{ route('reportes.notas-entrega.pdf', request()->query()) }}">PDF de notas</a>
        @if(auth()->user()->hasPermission('ventas'))
            <a class="btn" href="{{ route('notas.create') }}">Crear nota de entrega</a>
        @endif
    </div>
    <table>
        <tr><th>Fecha</th><th>No.</th><th>Nombre comercial</th><th>NIT</th><th>Cliente</th><th>Total</th><th>Estado de cobro</th><th>Acciones</th></tr>
        @forelse($notas as $nota)
            <tr>
                <td>{{ $nota->fecha->format('d/m/Y') }}</td>
                <td>{{ $nota->numero_venta }}</td>
                <td>{{ $nota->nombre_comercial }}</td>
                <td>{{ $nota->nit_cliente }}</td>
                <td>{{ $nota->cliente?->nombre_mostrable ?? 'Sin cliente' }}</td>
                <td>Bs {{ number_format($nota->total, 2) }}</td>
                @php($notaPagado = (float) $nota->cobros->where('estado', 'registrado')->sum('importe'))
                <td>{{ $notaPagado >= (float) $nota->total ? 'Pagada' : ($notaPagado > 0 ? 'Pago parcial' : 'Por cobrar') }}<br><small>Saldo: Bs {{ number_format(max(0, (float) $nota->total - $notaPagado), 2) }}</small></td>
                <td>
                    @if(auth()->user()->hasPermission('ventas'))
                        <div class="actions">
                            <a class="btn secondary" href="{{ route('notas.show', $nota) }}">Ver nota</a>
                            <a class="btn secondary" href="{{ route('notas.show', $nota) }}#cobros">Cobros</a>
                            <a class="btn secondary" href="{{ route('ventas.recibo', $nota) }}" target="_blank">Vista previa</a>
                            <a class="btn secondary" href="{{ route('ventas.pdf', $nota) }}">PDF</a>
                            <a class="btn secondary" href="{{ route('ventas.historial.edit', $nota) }}">Editar</a>
                        </div>
                    @else
                        <span class="muted">Sin permiso</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8">Sin notas de entrega en el rango seleccionado.</td></tr>
        @endforelse
    </table>
    {{ $notas->fragment('notas-entrega')->links() }}
</section>

<div class="card" style="margin-top:16px;">
    <h2>Detalle de ventas facturadas</h2>
    <table>
        <tr><th>Fecha</th><th>No.</th><th>Documento</th><th>Nombre comercial</th><th>NIT</th><th>Cliente</th><th>Total</th><th>Estado de cobro</th><th>Acciones</th></tr>
        @forelse($ventas as $venta)
            <tr>
                <td>{{ $venta->fecha->format('d/m/Y') }}</td>
                <td>{{ $venta->numero_venta }}</td>
                <td>{{ $venta->tipo_documento === 'nota_entrega' ? 'Nota de entrega' : 'Factura' }}</td>
                <td>{{ $venta->nombre_comercial }}</td>
                <td>{{ $venta->nit_cliente }}</td>
                <td>{{ $venta->cliente?->nombre_mostrable ?? 'Sin cliente' }}</td>
                <td>Bs {{ number_format($venta->total, 2) }}</td>
                @php($ventaPagado = (float) $venta->cobros->where('estado', 'registrado')->sum('importe'))
                <td>{{ $ventaPagado >= (float) $venta->total ? 'Pagada' : ($ventaPagado > 0 ? 'Pago parcial' : 'Por cobrar') }}<br><small>Saldo: Bs {{ number_format(max(0, (float) $venta->total - $ventaPagado), 2) }}</small></td>
                <td>
                    @if(auth()->user()->hasPermission('ventas'))
                        <div class="actions" style="gap:8px;">
                            <a class="btn secondary" href="{{ route('ventas.recibo', $venta) }}" target="_blank">Vista previa</a>
                            <a class="btn secondary" href="{{ route('ventas.show', $venta) }}#cobros">Cobros</a>
                            <a class="btn secondary" href="{{ route('ventas.pdf', $venta) }}">PDF</a>
                            <a class="btn secondary" href="{{ route('ventas.historial.edit', $venta) }}">Editar</a>
                        </div>
                    @else
                        <span class="muted">Sin permiso</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9">Sin ventas registradas.</td></tr>
        @endforelse
    </table>
    {{ $ventas->links() }}
</div>

<div class="card" style="margin-top:16px;">
    <h2>Facturas de compra</h2>
    <table>
        <tr><th>Fecha</th><th>No.</th><th>Documento</th><th>Nombre comercial</th><th>NIT</th><th>Proveedor</th><th>Total</th><th>Acciones</th></tr>
        @forelse($compras as $compra)
            <tr>
                <td>{{ $compra->fecha->format('d/m/Y') }}</td>
                <td>{{ $compra->numero_compra }}</td>
                <td>Factura</td>
                <td>{{ $compra->nombre_comercial }}</td>
                <td>{{ $compra->nit_proveedor }}</td>
                <td>{{ $compra->proveedor?->nombre ?? 'Sin proveedor' }}</td>
                <td>Bs {{ number_format($compra->total, 2) }}</td>
                <td>
                    @if(auth()->user()->hasPermission('compras'))
                        <div class="actions" style="gap:8px;">
                            <a class="btn secondary" href="{{ route('compras.recibo', $compra) }}" target="_blank">Vista previa</a>
                            <a class="btn secondary" href="{{ route('compras.pdf', $compra) }}">PDF</a>
                            <a class="btn secondary" href="{{ route('compras.historial.edit', $compra) }}">Editar</a>
                        </div>
                    @else
                        <span class="muted">Sin permiso</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8">Sin facturas de compra registradas.</td></tr>
        @endforelse
    </table>
    {{ $compras->links() }}
</div>

<div class="card" style="margin-top:16px;">
    <h2>Notas de compra</h2>
    <table>
        <tr><th>Fecha</th><th>No.</th><th>Nombre comercial</th><th>NIT</th><th>Proveedor</th><th>Total</th><th>Acciones</th></tr>
        @forelse($notasCompras as $compra)
            <tr>
                <td>{{ $compra->fecha->format('d/m/Y') }}</td><td>{{ $compra->numero_compra }}</td><td>{{ $compra->nombre_comercial }}</td><td>{{ $compra->nit_proveedor }}</td><td>{{ $compra->proveedor?->nombre ?? 'Sin proveedor' }}</td><td>Bs {{ number_format($compra->total, 2) }}</td>
                <td>@if(auth()->user()->hasPermission('compras'))<div class="actions"><a class="btn secondary" href="{{ route('compras.recibo', $compra) }}" target="_blank">Vista previa</a><a class="btn secondary" href="{{ route('compras.pdf', $compra) }}">PDF</a><a class="btn secondary" href="{{ route('compras.historial.edit', $compra) }}">Editar</a></div>@else<span class="muted">Sin permiso</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7">Sin notas de compra registradas.</td></tr>
        @endforelse
    </table>
    {{ $notasCompras->links() }}
</div>

<div class="card" style="margin-top:16px;">
    <h2>Referencia tributaria</h2>
    <p class="muted">Cálculo interno sobre facturas: compras menos ventas, IVA 13%, IT 3% sobre ventas y Por pagar como suma de IVA más IT. Las notas de entrega se muestran separadas y no ingresan a este cálculo. El Excel descargado conserva el detalle y recalcula los importes cuando contabilidad corrige una copia de revisión.</p>
    <table>
        <tr><th>Empresa</th><th>Ventas facturadas</th><th>Compras facturadas</th><th>Diferencia</th><th>IVA 13%</th><th>IT 3%</th><th>Por pagar</th></tr>
        @foreach($tributarioPorNombre as $item)
            <tr>
                <td>{{ $item['nombre_comercial'] }}</td>
                <td>Bs {{ number_format($item['ventas_facturadas'], 2) }}</td>
                <td>Bs {{ number_format($item['compras_facturadas'], 2) }}</td>
                <td>Bs {{ number_format($item['diferencia_tributaria'], 2) }}</td>
                <td>Bs {{ number_format($item['iva_estimado'], 2) }}</td>
                <td>Bs {{ number_format($item['it_estimado'], 2) }}</td>
                <td @style(['color:#b42318;font-weight:800' => $item['por_pagar'] < 0])>Bs {{ number_format($item['por_pagar'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <th>Total</th><th>Bs {{ number_format($totales['ventas_facturadas'], 2) }}</th><th>Bs {{ number_format($totales['compras_facturadas'], 2) }}</th><th>Bs {{ number_format($totales['diferencia_tributaria'], 2) }}</th><th>Bs {{ number_format($totales['iva_estimado'], 2) }}</th><th>Bs {{ number_format($totales['it_estimado'], 2) }}</th><th @style(['color:#b42318' => $totales['por_pagar'] < 0])>Bs {{ number_format($totales['por_pagar'], 2) }}</th>
        </tr>
    </table>
</div>
@endsection
