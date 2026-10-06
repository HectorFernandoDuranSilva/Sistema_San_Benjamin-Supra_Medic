@extends('layouts.app')

@section('content')
<div class="top"><h1>Cotizaciones</h1><a class="btn" href="{{ route('cotizaciones.create') }}">Nueva cotizacion</a></div>
<div class="card">
    <table>
        <tr><th>No.</th><th>Nombre comercial</th><th>Fecha</th><th>Valida hasta</th><th>NIT</th><th>Cliente</th><th>Total</th><th>Estado</th><th>Usuario</th><th></th></tr>
        @foreach($cotizaciones as $cotizacion)
            <tr>
                <td>{{ $cotizacion->numero_cotizacion }}</td>
                <td>{{ $cotizacion->nombre_comercial }}</td>
                <td>{{ $cotizacion->fecha->format('d/m/Y') }}</td>
                <td>{{ optional($cotizacion->valida_hasta)->format('d/m/Y') ?? '-' }}</td>
                <td>{{ $cotizacion->nit_cliente }}</td>
                <td>{{ $cotizacion->cliente?->nombre_mostrable ?? 'Sin cliente' }}</td>
                <td>Bs {{ number_format($cotizacion->total, 2) }}</td>
                <td>{{ $cotizacion->estado }}@if($cotizacion->confirmada_at)<br><small>Confirmada</small>@else<br><small>Pendiente de revisión</small>@endif</td>
                <td>{{ $cotizacion->usuario?->name }}</td>
                <td><div class="actions" style="gap:8px;"><a class="btn secondary" href="{{ route('cotizaciones.show', $cotizacion) }}">{{ $cotizacion->confirmada_at ? 'Ver' : 'Revisar' }}</a>@if($cotizacion->confirmada_at)<a class="btn secondary" href="{{ route('cotizaciones.pdf', $cotizacion) }}">PDF</a><a class="btn secondary" href="{{ route('cotizaciones.word', $cotizacion) }}">Word</a>@endif @if($cotizacion->venta)<a class="btn secondary" href="{{ route('ventas.show', $cotizacion->venta) }}">Venta</a>@endif</div></td>
            </tr>
        @endforeach
    </table>
    {{ $cotizaciones->links() }}
</div>
@endsection
