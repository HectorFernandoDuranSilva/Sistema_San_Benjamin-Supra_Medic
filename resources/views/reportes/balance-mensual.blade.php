@extends('layouts.app')

@section('content')
<div class="top">
    <h1>Balance mensual</h1>
    <div class="actions">
        <a class="btn secondary" href="{{ route('reportes.balance-mensual.pdf', request()->query()) }}">Descargar PDF</a>
        <a class="btn secondary" href="{{ route('reportes.balance-mensual.excel', request()->query()) }}">Descargar Excel</a>
        <a class="btn secondary" href="{{ route('reportes.documentos') }}">Notas/Facturas</a>
        <a class="btn secondary" href="{{ route('reportes.ventas') }}">Ventas</a>
        <a class="btn secondary" href="{{ route('reportes.compras') }}">Compras</a>
    </div>
</div>

<form class="card" method="get" style="margin-bottom:12px;">
    <div class="row">
        <div class="field"><label>Mes</label><input type="month" name="mes" value="{{ $mes }}"></div>
        <div class="field"><label>Nombre comercial</label><select name="nombre_comercial"><option value="">Ambos</option><option value="San Benjamin" @selected($nombreComercial === 'San Benjamin')>San Benjamín</option><option value="Supra Medic" @selected($nombreComercial === 'Supra Medic')>Supra Medic</option></select></div>
        <div class="field" style="justify-content:end;"><button class="btn secondary">Ver balance</button></div>
    </div>
</form>

<form class="actions" method="post" action="{{ route('reportes.guardados.store') }}" style="margin-bottom:12px;">
    @csrf
    <input name="titulo" value="Balance mensual {{ $periodo->format('m/Y') }}">
    <input type="hidden" name="tipo" value="balance_mensual">
    <input type="hidden" name="ruta" value="{{ request()->getRequestUri() }}">
    <input name="observacion" placeholder="Observación">
    <button class="btn secondary">Guardar reporte actual</button>
</form>

<div class="grid cols-4" style="margin-bottom:16px;">
    <div class="metric"><span>Ventas facturadas</span><strong>Bs {{ number_format($totales['ventas_facturas'], 2) }}</strong></div>
    <div class="metric"><span>Compras facturadas</span><strong>Bs {{ number_format($totales['compras_facturadas'], 2) }}</strong></div>
    <div class="metric"><span>Diferencia</span><strong>Bs {{ number_format($totales['diferencia_tributaria'], 2) }}</strong></div>
    <div class="metric"><span>Por pagar</span><strong @style(['color:#b42318;font-weight:800' => $totales['por_pagar'] < 0])>Bs {{ number_format($totales['por_pagar'], 2) }}</strong></div>
</div>

<div class="grid cols-4" style="margin-bottom:16px;">
    <div class="metric"><span>IVA 13%</span><strong>Bs {{ number_format($totales['iva_estimado'], 2) }}</strong></div>
    <div class="metric"><span>IT 3% sobre ventas</span><strong>Bs {{ number_format($totales['it_estimado'], 2) }}</strong></div>
    <div class="metric"><span>Resultado comercial</span><strong>Bs {{ number_format($totales['resultado'], 2) }}</strong></div>
    <div class="metric"><span>Gastos operativos</span><strong>Bs {{ number_format($totales['gastos_operativos'], 2) }}</strong><a class="btn secondary" style="margin-top:8px;align-self:flex-start;" href="{{ route('reportes.gastos-operativos', ['mes' => $mes, 'nombre_comercial' => $nombreComercial]) }}">Ver gastos</a></div>
    <div class="metric"><span>Notas de entrega</span><strong>Bs {{ number_format($totales['notas_entrega'], 2) }}</strong><small>No ingresan al balance ni a IVA o IT.</small><a class="btn secondary" style="margin-top:8px;align-self:flex-start;" href="{{ route('reportes.notas-entrega.pdf', ['mes' => $mes, 'nombre_comercial' => $nombreComercial]) }}">PDF de notas</a></div>
</div>

<div class="grid cols-2">
    <section class="card">
        <h2>Movimiento por nombre comercial</h2>
        <div class="compact-list">
            @foreach($porNombre as $item)
                <div class="alert-item">
                    <strong><span>{{ $item['nombre_comercial'] }}</span><span>Bs {{ number_format($item['resultado'], 2) }}</span></strong>
                    <div style="display:grid;gap:8px;margin-top:10px;">
                        <div>
                            <small>Ventas Bs {{ number_format($item['ventas_total'], 2) }}</small>
                            <div style="height:10px;border-radius:999px;background:rgba(103,190,217,.18);overflow:hidden;"><span style="display:block;height:100%;width:{{ min(100, round(($item['ventas_total'] / $maxGrafico) * 100, 2)) }}%;background:var(--primary);"></span></div>
                        </div>
                        <div>
                            <small>Compras Bs {{ number_format($item['compras_total'], 2) }}</small>
                            <div style="height:10px;border-radius:999px;background:rgba(103,190,217,.18);overflow:hidden;"><span style="display:block;height:100%;width:{{ min(100, round(($item['compras_total'] / $maxGrafico) * 100, 2)) }}%;background:#1e3a5f;"></span></div>
                        </div>
                        <div><small>Gastos operativos Bs {{ number_format($item['gastos_operativos'], 2) }}</small></div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <h2>Referencia tributaria</h2>
        <p class="muted">Cálculo interno solo sobre facturas: compras menos ventas, IVA 13%, IT 3% sobre ventas y Por pagar como suma de IVA más IT. Las notas de entrega se consultan por separado en Notas/Facturas y no ingresan a este balance.</p>
        <table>
            <tr><th>Concepto</th><th>Total</th></tr>
            <tr><td>Ventas facturadas</td><td>Bs {{ number_format($totales['ventas_facturas'], 2) }}</td></tr>
            <tr><td>Compras facturadas</td><td>Bs {{ number_format($totales['compras_facturas'], 2) }}</td></tr>
            <tr><td>Diferencia (compras - ventas)</td><td>Bs {{ number_format($totales['diferencia_tributaria'], 2) }}</td></tr>
            <tr><td>IVA 13%</td><td>Bs {{ number_format($totales['iva_estimado'], 2) }}</td></tr>
            <tr><td>IT 3% sobre ventas</td><td>Bs {{ number_format($totales['it_estimado'], 2) }}</td></tr>
            <tr><td>Por pagar</td><td @style(['color:#b42318;font-weight:800' => $totales['por_pagar'] < 0])>Bs {{ number_format($totales['por_pagar'], 2) }}</td></tr>
            <tr><td>Gastos operativos</td><td>Bs {{ number_format($totales['gastos_operativos'], 2) }}</td></tr>
        </table>
    </section>
</div>

<div class="card" style="margin-top:16px;">
    <h2>Detalle mensual</h2>
    <table>
        <tr>
            <th>Nombre comercial</th>
            <th>Ventas facturas</th>
            <th>Compras facturas</th>
            <th>Gastos operativos</th>
            <th>Diferencia</th>
            <th>IVA 13%</th>
            <th>IT 3%</th>
            <th>Por pagar</th>
            <th>Resultado comercial</th>
        </tr>
        @foreach($porNombre as $item)
            <tr>
                <td>{{ $item['nombre_comercial'] }}</td>
                <td>Bs {{ number_format($item['ventas_facturas'], 2) }}</td>
                <td>Bs {{ number_format($item['compras_facturas'], 2) }}</td>
                <td>Bs {{ number_format($item['gastos_operativos'], 2) }}</td>
                <td>Bs {{ number_format($item['diferencia_tributaria'], 2) }}</td>
                <td>Bs {{ number_format($item['iva_estimado'], 2) }}</td>
                <td>Bs {{ number_format($item['it_estimado'], 2) }}</td>
                <td @style(['color:#b42318;font-weight:800' => $item['por_pagar'] < 0])>Bs {{ number_format($item['por_pagar'], 2) }}</td>
                <td>Bs {{ number_format($item['resultado'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <th>Total</th>
            <th>Bs {{ number_format($totales['ventas_facturas'], 2) }}</th>
            <th>Bs {{ number_format($totales['compras_facturas'], 2) }}</th>
            <th>Bs {{ number_format($totales['gastos_operativos'], 2) }}</th>
            <th>Bs {{ number_format($totales['diferencia_tributaria'], 2) }}</th>
            <th>Bs {{ number_format($totales['iva_estimado'], 2) }}</th>
            <th>Bs {{ number_format($totales['it_estimado'], 2) }}</th>
            <th @style(['color:#b42318' => $totales['por_pagar'] < 0])>Bs {{ number_format($totales['por_pagar'], 2) }}</th>
            <th>Bs {{ number_format($totales['resultado'], 2) }}</th>
        </tr>
    </table>
</div>
@endsection
