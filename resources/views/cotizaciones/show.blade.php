@extends('layouts.app')

@section('content')
<div class="top">
    <h1>Cotizacion {{ $cotizacion->numero_cotizacion }}</h1>
    <div class="actions">
        @if($cotizacion->confirmada_at)
            <a class="btn secondary" href="{{ route('cotizaciones.pdf', $cotizacion) }}">PDF</a>
            <a class="btn secondary" href="{{ route('cotizaciones.word', $cotizacion) }}">Word</a>
        @endif
        @if(! $cotizacion->venta_id)
            <a class="btn secondary" href="{{ route('cotizaciones.edit', $cotizacion) }}">Editar cotización</a>
            <a class="btn secondary" href="#convertir-cotizacion">Convertir</a>
        @else
            <a class="btn secondary" href="{{ route('ventas.show', $cotizacion->venta) }}">Ver venta</a>
        @endif
    </div>
</div>

@if(! $cotizacion->confirmada_at)
    <form class="card" method="post" action="{{ route('cotizaciones.confirm', $cotizacion) }}">
        @csrf
        <h2>Revisión antes de exportar</h2>
        <p>Verifique cliente, productos, cantidades, precios, marca, procedencia, vencimiento y condiciones. Al confirmar se habilitan las descargas en PDF y Word.</p>
        <div class="row">
            <label class="field" style="flex-direction:row;align-items:center;gap:8px;">
                <input type="checkbox" name="confirmacion" value="1" @checked(old('confirmacion'))>
                Confirmo que los datos de esta cotización están correctos.
            </label>
            <div class="field" style="justify-content:end;">
                <button class="btn">Confirmar cotización</button>
            </div>
        </div>
        <small>Al editar la cotización, deberá revisarla y confirmarla nuevamente antes de exportarla.</small>
    </form>
@else
    <div class="card">
        <strong>Cotización confirmada</strong>
        el {{ $cotizacion->confirmada_at->format('d/m/Y H:i') }}
        @if($cotizacion->confirmadaPor) por {{ $cotizacion->confirmadaPor->name }}@endif.
        Puede descargarla en PDF o Word.
    </div>
@endif

@if(! $cotizacion->venta_id)
    <form id="convertir-cotizacion" class="card" method="post" action="{{ route('cotizaciones.convertir', $cotizacion) }}" data-glass-confirm="Esta acción convertira la cotizacion en venta y descontará stock según los lotes disponibles. ¿Desea continuar?">
        @csrf
        <h2>Convertir cotizacion</h2>
        <div class="row">
            <div class="field">
                <label>Documento destino</label>
                <select name="tipo_documento">
                    <option value="factura">Factura</option>
                    <option value="nota_entrega">Nota de entrega</option>
                </select>
            </div>
            <div class="field">
                <label>Fecha</label>
                <input type="date" name="fecha" value="{{ now()->toDateString() }}">
            </div>
            <div class="field">
                <label>Factura / nota</label>
                <input name="factura" value="{{ $cotizacion->numero_cotizacion }}" maxlength="60">
            </div>
            <div class="field" style="justify-content:end;">
                <button class="btn">Convertir a venta</button>
            </div>
        </div>
    </form>
@endif

<div class="card">
    <p>
        <strong>Nombre comercial:</strong> {{ $cotizacion->nombre_comercial ?? '-' }} |
        <strong>Fecha:</strong> {{ $cotizacion->fecha->format('d/m/Y') }} |
        <strong>Valida hasta:</strong> {{ optional($cotizacion->valida_hasta)->format('d/m/Y') ?? '-' }} |
        <strong>NIT:</strong> {{ $cotizacion->nit_cliente ?: '-' }} |
        <strong>Cliente:</strong> {{ $cotizacion->cliente?->nombre_mostrable ?? 'Sin cliente' }} |
        <strong>Estado:</strong> {{ $cotizacion->estado }}
    </p>
    <p><strong>Comentario:</strong> {{ $cotizacion->observacion ?: '-' }}</p>

    <table>
        <tr><th>Codigo</th><th>Producto</th><th>Stock</th><th>Cantidad</th><th>Costo ref.</th><th>%</th><th>Precio</th><th>Desc</th><th>Subtotal</th><th>Impuesto</th><th>Total</th></tr>
        @foreach($cotizacion->detalles as $detalle)
            @php($stockDisponible = (float) ($detalle->producto?->stock_actual ?? 0))
            <tr>
                <td>{{ $detalle->codigo_producto }}</td>
                <td>{{ $detalle->nombre_producto }}<br>
                    <small>Marca: {{ $detalle->marca ?: '-' }} · Procedencia: {{ $detalle->procedencia ?: '-' }}<br>
                    Vencimiento: {{ $detalle->fecha_vencimiento?->format('d/m/Y') ?: '-' }}</small>
                </td>
                <td>@if(!$detalle->producto_id)<span class="pill">Por pedido</span>@else{{ number_format($stockDisponible, 2) }} @if($stockDisponible < (float) $detalle->cantidad)<span class="pill">Revisar stock</span>@endif @endif</td>
                <td>{{ number_format($detalle->cantidad, 2) }}</td>
                <td>Bs {{ number_format($detalle->costo_referencial, 2) }}</td>
                <td>{{ number_format($detalle->porcentaje_utilidad, 2) }}%</td>
                <td>Bs {{ number_format($detalle->precio_unitario, 2) }}</td>
                <td>Bs {{ number_format($detalle->descuento, 2) }}</td>
                <td>Bs {{ number_format($detalle->subtotal, 2) }}</td>
                <td>Bs {{ number_format($detalle->impuesto, 2) }}</td>
                <td>Bs {{ number_format($detalle->total, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <h2 style="text-align:right;margin-top:12px;">
        Subtotal Bs {{ number_format($cotizacion->subtotal, 2) }} |
        Desc Bs {{ number_format($cotizacion->descuento, 2) }} |
        Imp Bs {{ number_format($cotizacion->impuesto_total, 2) }} |
        Total Bs {{ number_format($cotizacion->total, 2) }}
    </h2>
</div>
@endsection
