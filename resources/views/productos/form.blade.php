@extends('layouts.app')

@section('content')
<div class="top">
    <h1>{{ $producto->exists ? 'Editar producto' : 'Crear producto' }}</h1>
    <div class="actions">
        <a class="btn secondary" href="{{ route('categorias.index') }}">Crear categoría</a>
    </div>
</div>
@if(isset($tipoCambioActual) && $tipoCambioActual)
    <div class="notice">Tipo de cambio vigente: Bs {{ number_format($tipoCambioActual->valor, 2) }}. Fuente: {{ $tipoCambioActual->fuente }}. Fecha: {{ $tipoCambioActual->fecha->format('d/m/Y') }}.</div>
@else
    <div class="notice">No hay tipo de cambio guardado. Se usara el valor de respaldo hasta ejecutar la actualizacion del BCB.</div>
@endif
<form class="card" method="post" action="{{ $producto->exists ? route('productos.update', $producto) : route('productos.store') }}">
    @csrf
    @if($producto->exists) @method('put') @endif
    <div class="row">
        <div class="field"><label>Código</label><input name="codigo" value="{{ old('codigo', $producto->codigo) }}" required></div>
        <div class="field"><label>Nombre</label><input name="nombre" value="{{ old('nombre', $producto->nombre) }}" required></div>
        <div class="field"><label>Categoría</label><select name="categoria_id"><option value="">Sin categoría</option>@foreach($categorias as $c)<option value="{{ $c->id }}" @selected(old('categoria_id', $producto->categoria_id)==$c->id)>{{ $c->nombre }}</option>@endforeach</select></div>
        <div class="field"><label>Medida</label><input name="medida" value="{{ old('medida', $producto->medida) }}"></div>
    </div>
    <div class="row">
        <div class="field"><label for="marca">Marca</label><input id="marca" name="marca" maxlength="255" value="{{ old('marca', $producto->marca) }}" required></div>
        <div class="field"><label for="procedencia">Procedencia</label><input id="procedencia" name="procedencia" maxlength="255" value="{{ old('procedencia', $producto->procedencia) }}" required></div>
    </div>
    <div class="row">
        <div class="field"><label>Ubicación</label><input name="ubicacion" value="{{ old('ubicacion', $producto->ubicacion) }}"></div>
        <div class="field"><label>Moneda del costo</label><select id="moneda-costo" name="moneda_costo"><option value="usd" @selected(old('moneda_costo', $producto->moneda_costo ?? 'usd') === 'usd')>Dólares (USD)</option><option value="bs" @selected(old('moneda_costo', $producto->moneda_costo ?? 'usd') === 'bs')>Bolivianos (Bs)</option></select></div>
        <div class="field"><label>Precio ingreso USD</label><input id="precio-ingreso-usd" type="number" step="0.01" min="0" name="precio_ingreso_usd" value="{{ old('precio_ingreso_usd', $producto->precio_ingreso_usd ?? 0) }}"></div>
        <div class="field"><label>Costo ingreso Bs</label><input id="costo-ingreso-bs" type="number" step="0.01" min="0" name="costo_ingreso_bs" value="{{ old('costo_ingreso_bs', $producto->costo_referencial ?? 0) }}"></div>
    </div>
    <div class="row">
        <div class="field"><label>Tipo de cambio</label><input id="tipo-cambio" type="number" step="0.01" min="0.01" name="tipo_cambio" value="{{ number_format((float) old('tipo_cambio', $producto->tipo_cambio ?? 6.96), 2, '.', '') }}" required></div>
        <div class="field"><label>Recargo sobre costo (%) 25 a 30</label><input id="porcentaje-utilidad" type="number" step="0.01" min="25" max="30" name="porcentaje_utilidad" value="{{ old('porcentaje_utilidad', $producto->porcentaje_utilidad ?? 27) }}" required></div>
        <div class="field"><label>Costo de reposición Bs</label><input id="costo-referencial" type="number" step="0.01" value="{{ old('costo_referencial', $producto->costo_referencial ?? 0) }}" readonly></div>
        <div class="field"><label>Sugerido por reposición Bs</label><input id="precio-venta" type="number" step="0.01" value="{{ number_format($producto->costo_referencial * (1 + $producto->porcentaje_utilidad / 100), 2, '.', '') }}" readonly></div>
        @if($producto->exists)<div class="field"><label>Precio vigente Bs</label><input value="{{ number_format($producto->precio_venta, 2) }}" readonly><a class="btn secondary" href="{{ route('productos.show', $producto) }}">Revisar y confirmar precio</a></div>@endif
        <div class="field"><label>Stock mínimo</label><input type="number" step="0.01" min="0" name="stock_minimo" value="{{ old('stock_minimo', $producto->stock_minimo ?? 0) }}"></div>
    </div>
    @unless($producto->exists)
        <div class="row">
            <div class="field"><label>Stock inicial</label><input type="number" step="0.01" min="0" name="stock_inicial" value="{{ old('stock_inicial', 0) }}"></div>
            <div class="field"><label>Número de lote inicial</label><input name="codigo_lote" value="{{ old('codigo_lote') }}"></div>
            <div class="field"><label>Fecha de vencimiento inicial</label><input type="date" name="fecha_vencimiento" value="{{ old('fecha_vencimiento') }}"></div>
        </div>
    @endunless
    <div class="field"><label>Descripción</label><textarea name="descripcion">{{ old('descripcion', $producto->descripcion) }}</textarea></div>
    <label><input style="width:auto;" type="checkbox" name="activo" value="1" @checked(old('activo', $producto->activo ?? true))> Activo</label>
    <div class="actions" style="margin-top:14px;"><button class="btn">Guardar</button><a class="btn secondary" href="{{ route('inventario.index') }}">Volver a inventario</a></div>
</form>

@if($producto->exists)
    <section class="card" style="margin-top:16px;">
        <h1 style="font-size:20px;">Agregar lote al producto</h1>
        <form method="post" action="{{ route('productos.lotes.store', $producto) }}">
            @csrf
            <div class="row">
                <div class="field"><label>Cantidad</label><input type="number" step="0.01" min="0.01" name="cantidad" value="{{ old('cantidad', 1) }}" required></div>
                <div class="field"><label>Número de lote</label><input name="codigo_lote" value="{{ old('codigo_lote') }}"></div>
                <div class="field"><label>Fecha de vencimiento</label><input type="date" name="fecha_vencimiento" value="{{ old('fecha_vencimiento') }}"></div>
                <div class="field"><label>Costo unitario Bs</label><input type="number" step="0.01" min="0" name="costo_unitario" value="{{ old('costo_unitario', $producto->costo_referencial ?? 0) }}"></div>
            </div>
            <div class="field"><label>Observación</label><input name="observacion" value="{{ old('observacion') }}" placeholder="Entrada manual de lote"></div>
            <div class="actions"><button class="btn">Agregar lote</button></div>
        </form>
    </section>

    <section class="card" style="margin-top:16px;">
        <div class="top">
            <h1 style="font-size:20px;">Lotes y vencimientos del producto</h1>
            <div class="actions">
                <a class="btn secondary" href="{{ route('productos.show', $producto) }}">Ver producto</a>
                <a class="btn secondary" href="{{ route('inventario.lotes.index', ['producto_id' => $producto->id]) }}">Editar lotes</a>
                <a class="btn secondary" href="{{ route('compras.create') }}">Agregar por compra</a>
            </div>
        </div>
        <table>
            <tr><th>Lote</th><th>Vencimiento</th><th>Inicial</th><th>Consumido</th><th>Disponible</th><th>Estado</th></tr>
            @forelse($lotes as $lote)
                <tr>
                    <td>{{ $lote->codigo_lote ?: 'Sin lote' }}</td>
                    <td>{{ optional($lote->fecha_vencimiento)->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ number_format($lote->cantidad_inicial, 2) }}</td>
                    <td>{{ number_format((float) ($lote->cantidad_consumida ?? 0), 2) }}</td>
                    <td>{{ number_format($lote->cantidad_disponible, 2) }}</td>
                    <td>{{ ucfirst($lote->estado) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Este producto aun no tiene lotes registrados.</td></tr>
            @endforelse
        </table>
    </section>
@endif
<script>
function updateProductPrice() {
    const moneda = document.getElementById('moneda-costo').value;
    const ingreso = parseFloat(document.getElementById('precio-ingreso-usd').value || '0');
    const ingresoBs = parseFloat(document.getElementById('costo-ingreso-bs').value || '0');
    const cambio = parseFloat(document.getElementById('tipo-cambio').value || '0');
    const porcentaje = parseFloat(document.getElementById('porcentaje-utilidad').value || '0');
    const costo = moneda === 'bs' ? ingresoBs : ingreso * cambio;
    const precio = costo * (1 + (porcentaje / 100));

    document.getElementById('costo-referencial').value = costo.toFixed(2);
    document.getElementById('precio-venta').value = precio.toFixed(2);
}

['moneda-costo', 'precio-ingreso-usd', 'costo-ingreso-bs', 'tipo-cambio', 'porcentaje-utilidad'].forEach(function (id) {
    document.getElementById(id).addEventListener('input', updateProductPrice);
    document.getElementById(id).addEventListener('change', updateProductPrice);
});
updateProductPrice();
</script>
@endsection

