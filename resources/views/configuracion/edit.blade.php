@extends('layouts.app')

@section('content')
<div class="top"><h1>Configuracion de empresa</h1></div>
<form class="card" method="post" action="{{ route('configuracion.update') }}">@csrf @method('put')
<div class="row"><div class="field"><label>Nombre comercial seleccionado</label><input value="{{ $nombreComercial }}" readonly></div><div class="field"><label>Telefono</label><input name="telefono" value="{{ old('telefono', $empresa->telefono) }}"></div><div class="field"><label>Moneda</label><input name="moneda" value="{{ old('moneda', $empresa->moneda) }}"></div><div class="field"><label>Tamano papel</label><select name="tamano_papel_recibo"><option value="letter" @selected($empresa->tamano_papel_recibo==='letter')>Carta</option><option value="a4" @selected($empresa->tamano_papel_recibo==='a4')>A4</option></select></div></div>
<div class="row"><div class="field"><label>Dias para alerta de vencimiento</label><input type="number" min="1" max="365" name="dias_alerta_vencimiento" value="{{ old('dias_alerta_vencimiento', $empresa->dias_alerta_vencimiento ?? 30) }}"></div><div class="field"><label>Dias de atraso para alerta de cuotas</label><input type="number" min="1" max="365" name="dias_alerta_cuotas" value="{{ old('dias_alerta_cuotas', $empresa->dias_alerta_cuotas ?? 10) }}"></div></div>
@php($dashboardOpciones = old('dashboard_opciones', $empresa->dashboard_opciones ?? []))
@php($dashboardOpcionesPorRol = old('dashboard_opciones_por_rol', $empresa->dashboard_opciones_por_rol ?? []))
@php($dashboardItems = ['ventas_hoy' => 'Ventas del día', 'total_vendido' => 'Total vendido', 'stock_bajo' => 'Stock bajo', 'stock_pendiente' => 'Faltantes por regularizar', 'movimientos' => 'Movimientos de inventario', 'cuentas_cobrar' => 'Cuentas por cobrar', 'alertas_datos' => 'Alertas de datos', 'faltantes_stock' => 'Aviso de faltantes de stock', 'vencimientos' => 'Vencimientos', 'cuotas' => 'Cuotas por cobrar', 'movimiento_mensual' => 'Movimiento mensual', 'ultimas_operaciones' => 'Últimas operaciones', 'facturacion_mensual' => 'Aviso de facturación mensual'])
<h2 style="font-size:18px;">Dashboard</h2>
<p>Puede ocultar indicadores, bloques y alertas. Los cálculos se mantienen basados en las operaciones registradas.</p>
<h3 style="font-size:16px;">Vista predeterminada</h3>
<div class="row">
@foreach($dashboardItems as $key => $label)
    <label class="field" style="flex-direction:row;align-items:center;gap:8px;"><input type="hidden" name="dashboard_opciones[{{ $key }}]" value="0"><input type="checkbox" name="dashboard_opciones[{{ $key }}]" value="1" @checked(($dashboardOpciones[$key] ?? true))>{{ $label }}</label>
@endforeach
</div>
<p class="muted">La configuración del rol reemplaza esta vista predeterminada solo para los usuarios de ese rol.</p>
@foreach(['administrador' => 'Administradores', 'operador' => 'Operadores'] as $role => $roleLabel)
    <h3 style="font-size:16px;margin-top:20px;">{{ $roleLabel }}</h3>
    <div class="row">
    @foreach($dashboardItems as $key => $label)
        <label class="field" style="flex-direction:row;align-items:center;gap:8px;"><input type="hidden" name="dashboard_opciones_por_rol[{{ $role }}][{{ $key }}]" value="0"><input type="checkbox" name="dashboard_opciones_por_rol[{{ $role }}][{{ $key }}]" value="1" @checked(($dashboardOpcionesPorRol[$role][$key] ?? $dashboardOpciones[$key] ?? true))>{{ $label }}</label>
    @endforeach
    </div>
@endforeach
<div class="field"><label>Direccion</label><input name="direccion" value="{{ old('direccion', $empresa->direccion) }}"></div>
<div class="field"><label>Texto pie recibos</label><textarea name="texto_pie_recibos">{{ old('texto_pie_recibos', $empresa->texto_pie_recibos) }}</textarea></div>
<button class="btn">Guardar</button>
</form>
@endsection
