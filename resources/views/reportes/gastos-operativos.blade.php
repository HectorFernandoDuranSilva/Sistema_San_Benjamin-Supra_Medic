@extends('layouts.app')

@section('content')
<div class="top">
    <h1>Gastos operativos</h1>
    <div class="actions">
        <a class="btn secondary" href="{{ route('reportes.documentos') }}">Notas y facturas</a>
        @if(auth()->user()->hasPermission('balance_mensual'))
            <a class="btn secondary" href="{{ route('reportes.balance-mensual') }}">Balance mensual</a>
        @endif
    </div>
</div>

<form class="card" method="post" action="{{ route('reportes.gastos-operativos.store') }}">
    @csrf
    <div class="row">
        <div class="field"><label>Nombre comercial</label><select name="nombre_comercial" required><option value="San Benjamin" @selected(old('nombre_comercial', $nombreComercialFormulario) === 'San Benjamin')>San Benjamin</option><option value="Supra Medic" @selected(old('nombre_comercial', $nombreComercialFormulario) === 'Supra Medic')>Supra Medic</option></select></div>
        <div class="field"><label>Tipo</label><select name="tipo" required><option value="combustible">Combustible</option><option value="envio">Envío</option><option value="transporte">Transporte</option><option value="recojo">Recojo</option><option value="otro">Otro</option></select></div>
        <div class="field"><label>Operación</label><select name="modalidad" required><option value="envio">Envío</option><option value="recojo">Recojo</option><option value="otro">Otro</option></select></div>
        <div class="field"><label>Respaldo</label><select name="documento_tipo" required><option value="factura">Factura</option><option value="nota">Nota</option><option value="guia">Guía</option><option value="sin_documento">Sin documento</option></select></div>
        <div class="field"><label>Número</label><input name="documento_numero" value="{{ old('documento_numero') }}" maxlength="255"></div>
        <div class="field"><label>Fecha</label><input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" required></div>
        <div class="field"><label>Monto Bs</label><input type="number" step="0.01" min="0.01" name="importe" value="{{ old('importe') }}" required></div>
        <div class="field"><label>Descripción</label><input name="descripcion" value="{{ old('descripcion') }}" maxlength="255"></div>
        <div class="field"><label>Observación</label><input name="observacion" value="{{ old('observacion') }}" maxlength="1000"></div>
    </div>
    <div class="actions"><button class="btn">Registrar gasto</button></div>
</form>

<form class="card" method="get" style="margin-top:16px;">
    <div class="row">
        <div class="field"><label>Mes</label><input type="month" name="mes" value="{{ $mes }}"></div>
        <div class="field"><label>Nombre comercial</label><select name="nombre_comercial"><option value="">Ambos</option><option value="San Benjamin" @selected($nombreComercial === 'San Benjamin')>San Benjamin</option><option value="Supra Medic" @selected($nombreComercial === 'Supra Medic')>Supra Medic</option></select></div>
        <div class="field" style="justify-content:end;"><button class="btn secondary">Ver gastos</button></div>
    </div>
</form>

<section class="card" style="margin-top:16px;">
    <div class="top"><h2>Gastos registrados</h2><strong>Bs {{ number_format($totalGastos, 2) }}</strong></div>
    <table>
        <tr><th>Fecha</th><th>Empresa</th><th>Tipo</th><th>Operación</th><th>Respaldo</th><th>Descripción</th><th>Monto</th><th>Registró</th><th>Acciones</th></tr>
        @forelse($gastos as $gasto)
            <tr><td>{{ $gasto->fecha->format('d/m/Y') }}</td><td>{{ $gasto->nombre_comercial }}</td><td>{{ ucfirst($gasto->tipo) }}</td><td>{{ ucfirst($gasto->modalidad) }}</td><td>{{ ucfirst(str_replace('_', ' ', $gasto->documento_tipo)) }} {{ $gasto->documento_numero ?: '-' }}</td><td>{{ $gasto->descripcion ?: '-' }}</td><td>Bs {{ number_format($gasto->importe, 2) }}</td><td>{{ $gasto->usuario?->name ?? 'Sin usuario' }}</td><td><a class="btn secondary" href="{{ route('reportes.gastos-operativos.pdf', $gasto) }}">PDF</a></td></tr>
        @empty
            <tr><td colspan="9">Sin gastos operativos en el período seleccionado.</td></tr>
        @endforelse
    </table>
    {{ $gastos->links() }}
</section>

@if($historicos->isNotEmpty())
<section class="card" style="margin-top:16px;">
    <h2>Gastos históricos vinculados a compras</h2>
    <table>
        <tr><th>Fecha</th><th>Compra</th><th>Proveedor</th><th>Tipo</th><th>Respaldo</th><th>Descripción</th><th>Monto</th></tr>
        @foreach($historicos as $gasto)
            <tr><td>{{ $gasto->fecha?->format('d/m/Y') }}</td><td>{{ $gasto->compra?->numero_compra ?? '-' }}</td><td>{{ $gasto->compra?->proveedor?->nombre ?? '-' }}</td><td>{{ ucfirst($gasto->tipo) }}</td><td>{{ ucfirst(str_replace('_', ' ', $gasto->documento_tipo)) }} {{ $gasto->documento_numero ?: '-' }}</td><td>{{ $gasto->descripcion ?: '-' }}</td><td>Bs {{ number_format($gasto->importe, 2) }}</td></tr>
        @endforeach
    </table>
</section>
@endif
@endsection
