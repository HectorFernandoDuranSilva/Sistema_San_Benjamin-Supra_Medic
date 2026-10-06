@extends('layouts.app')

@section('content')
<style>#articulo-pedido[hidden], #articulo-catalogo[hidden] { display: none; }</style>
<div class="top">
    <h1>{{ $cotizacion ? 'Editar cotización' : 'Registro de cotización' }}</h1>
    <div class="actions"><a class="btn secondary" href="{{ route('clientes.index') }}">Crear cliente</a></div>
</div>
<div class="notice">Tipo de cambio vigente: Bs {{ number_format($tipoCambioValor, 2) }}@if($tipoCambioActual) - {{ $tipoCambioActual->fuente }} {{ $tipoCambioActual->fecha->format('d/m/Y') }}@endif.</div>
<form id="quotation-form" class="card" method="post" action="{{ $cotizacion ? route('cotizaciones.update', $cotizacion) : route('cotizaciones.store') }}">
@csrf
@if($cotizacion) @method('put')<input type="hidden" name="revision" value="{{ old('revision', $cotizacion->revision) }}">@endif
<div class="row">
    <div class="field"><label>Nombre comercial</label><select name="nombre_comercial"><option value="San Benjamin" @selected(old('nombre_comercial', $cotizacion?->nombre_comercial ?? $commercial['name'] ?? '') === 'San Benjamin')>San Benjamin</option><option value="Supra Medic" @selected(old('nombre_comercial', $cotizacion?->nombre_comercial ?? $commercial['name'] ?? '') === 'Supra Medic')>Supra Medic</option></select></div>
    <div class="field"><label>Fecha</label><input type="date" name="fecha" value="{{ old('fecha', $cotizacion?->fecha?->toDateString() ?? now()->toDateString()) }}" required></div>
    <div class="field"><label>Válida hasta</label><input type="date" name="valida_hasta" value="{{ old('valida_hasta', $cotizacion ? $cotizacion->valida_hasta?->toDateString() : now()->addDays(15)->toDateString()) }}"></div>
    <div class="field"><label>No.</label><input name="numero_cotizacion" value="{{ old('numero_cotizacion', $cotizacion?->numero_cotizacion ?? $siguienteNumero) }}" @readonly($cotizacion)></div>
</div>

<div class="row">
    <div class="field"><label>NIT</label><input id="nit-cliente" name="nit_cliente" value="{{ old('nit_cliente', $cotizacion?->nit_cliente) }}"></div>
    <div class="field"><label>Buscar cliente</label><input id="buscar-cliente" placeholder="Nombre o NIT"></div>
    <div class="field"><label>Cliente</label><select id="cliente-id" name="cliente_id"><option value="">Sin cliente</option>@foreach($clientes as $c)<option value="{{ $c->id }}" data-documento="{{ $c->documento }}" data-busqueda="{{ $c->nombre_mostrable }} {{ $c->nombre }} {{ $c->razon_social }} {{ $c->documento }}" @selected(old('cliente_id', $cotizacion?->cliente_id)==$c->id)>{{ $c->nombre_mostrable }}</option>@endforeach</select></div>
    <div class="field"><label>Descuento %</label><input id="descuento-porcentaje" type="number" step="0.01" min="0" max="100" name="descuento_porcentaje" value="{{ old('descuento_porcentaje', $cotizacion?->descuento_porcentaje ?? 0) }}"></div>
    <div class="field"><label>Lista de precios</label><select id="lista-precios"><option value="Precio de Venta">Precio de Venta</option><option value="Precio Sugerido">Precio sugerido por dólar/reposición</option><option value="Costo Referencial">Costo Referencial</option></select></div>
</div>

<fieldset style="border:0;padding:0;margin:12px 0;">
    <legend>Artículo</legend>
    <label style="display:inline-flex;margin-right:20px;gap:6px;"><input style="width:auto;" type="radio" name="modo_articulo" value="catalogo" checked> Del catálogo</label>
    <label style="display:inline-flex;gap:6px;"><input style="width:auto;" type="radio" name="modo_articulo" value="pedido"> Por pedido</label>
</fieldset>
<div id="articulo-catalogo" class="row">
    <div class="field"><label>Codigo</label><input id="codigo-producto" readonly></div>
    <div class="field"><label>Buscar producto</label><input id="buscar-producto" placeholder="Codigo o nombre"></div>
    <div class="field"><label>Producto</label><select id="producto-selector">@foreach($productos as $p)<option value="{{ $p->id }}">{{ $p->codigo }} - {{ $p->nombre }}</option>@endforeach</select></div>
    <div class="field"><label>Existencia</label><input id="existencia-producto" readonly></div>
</div>

<div id="articulo-pedido" class="row" hidden>
    <div class="field"><label for="nombre-pedido">Descripción del artículo</label><input id="nombre-pedido" maxlength="255"></div>
    <div class="field"><label for="medida-pedido">Presentación</label><input id="medida-pedido" maxlength="100"></div>
</div>

<div class="row">
    <div class="field"><label>Cantidad</label><input id="cantidad-producto" type="number" step="0.01" min="0.01" value="1"></div>
    <div class="field"><label>Costo referencial</label><input id="costo-producto" readonly value="0.00"></div>
    <div class="field"><label>Recargo sobre costo 25 a 30 %</label><input id="porcentaje-utilidad" type="number" step="0.01" min="25" max="30" value="27"></div>
    <div class="field"><label>Precio cotizado</label><input id="precio-producto" type="number" step="0.01" min="0" value="0"></div>
</div>

<div class="row">
    <div class="field"><label for="marca-producto">Marca</label><input id="marca-producto" maxlength="255"></div>
    <div class="field"><label for="procedencia-producto">Procedencia</label><input id="procedencia-producto" maxlength="255"></div>
    <div class="field"><label for="lote-producto">Lote</label><select id="lote-producto"><option value="">Sin lote seleccionado</option></select></div>
    <div class="field"><label for="vencimiento-producto">Vencimiento</label><input id="vencimiento-producto" type="date"></div>
</div>
<div class="actions" style="margin-bottom:12px;">
    <button id="agregar-producto" type="button" class="btn secondary" onclick="addSelectedItem()">Agregar producto</button>
</div>
<div class="field"><label>Comentario</label><textarea name="observacion">{{ old('observacion', $cotizacion?->observacion) }}</textarea></div>

<div class="card" style="margin-bottom:12px;">
    <h2>Datos del PDF</h2>
    <div class="row">
        <div class="field">
            <label>Empresa</label>
            <input id="footer-empresa" readonly>
        </div>
        <div class="field">
            <label>Dirección</label>
            <input name="footer_direccion" value="{{ old('footer_direccion', $cotizacion?->footer_direccion ?? $footerDefaults['direccion']) }}">
        </div>
        <div class="field">
            <label>Tiempo de validez de la oferta</label>
            <input name="footer_validez" value="{{ old('footer_validez', $cotizacion?->footer_validez ?? $footerDefaults['validez']) }}">
        </div>
    </div>
    <div class="row">
        <div class="field">
            <label>Tiempo de entrega</label>
            <textarea name="footer_entrega">{{ old('footer_entrega', $cotizacion?->footer_entrega ?? $footerDefaults['entrega']) }}</textarea>
        </div>
        <div class="field">
            <label>Condiciones de pago</label>
            <input name="footer_condiciones_pago" value="{{ old('footer_condiciones_pago', $cotizacion?->footer_condiciones_pago ?? $footerDefaults['condiciones_pago']) }}">
        </div>
        <div class="field">
            <label>Depósito a cuenta</label>
            <textarea name="footer_deposito_cuenta">{{ old('footer_deposito_cuenta', $cotizacion?->footer_deposito_cuenta ?? $footerDefaults['deposito_cuenta']) }}</textarea>
        </div>
    </div>
</div>

<div class="actions" style="margin-bottom:12px;">
    <button class="btn">{{ $cotizacion ? 'Guardar cambios' : 'Registrar cotización' }}</button>
</div>

<div class="card" style="padding:0;">
    <table id="items">
        <thead><tr><th>Codigo</th><th>Producto</th><th>Medida</th><th>Cantidad</th><th>Costo</th><th>%</th><th>Precio</th><th>Desc</th><th>Subtotal</th><th>Impuesto</th><th>Total</th><th></th></tr></thead>
        <tbody></tbody>
        <tfoot><tr><th colspan="7" style="text-align:right;">Totales</th><th id="total-descuento">0.00</th><th id="total-subtotal">0.00</th><th id="total-impuesto">0.00</th><th id="total-final">0.00</th><th></th></tr></tfoot>
    </table>
</div>
</form>

<div id="cotizacion-items-alert" class="glass-modal-backdrop" role="dialog" aria-modal="true">
    <div class="glass-modal is-error">
        <h2>Revise la cotización</h2>
        <p>Agregue al menos un producto para registrar la cotización.</p>
        <div class="glass-modal-actions">
            <button type="button" class="btn" data-glass-close>Entendido</button>
        </div>
    </div>
</div>

<script src="{{ asset('js/searchable-select.js') }}"></script>
<script>
const products = @json($productosCotizacion);
const initialItems = @json(old('items', $itemsIniciales));
const footerCompanies = @json($footerCompanies);
let idx = 0;
function escapeHtml(value) { const span = document.createElement('span'); span.textContent = String(value ?? ''); return span.innerHTML.replaceAll('"', '&quot;').replaceAll("'", '&#39;'); }
function money(value) { return (Number(value) || 0).toFixed(2); }
function isOrderMode() { return document.querySelector('[name="modo_articulo"]:checked').value === 'pedido'; }
function selectedProduct() {
    if (isOrderMode()) return undefined;
    const id = Number(document.getElementById('producto-selector').value);
    return products.find(product => Number(product.id) === id);
}
function calculatedPrice(product) {
    const list = document.getElementById('lista-precios').value;
    if (list === 'Costo Referencial') return product.cost;
    if (list === 'Precio Sugerido') return product.suggested || product.price;
    const porcentaje = parseFloat(document.getElementById('porcentaje-utilidad').value || product.margin || '27');
    const cost = parseFloat(product.cost || '0');
    return cost > 0 ? cost * (1 + porcentaje / 100) : product.price;
}
function refreshSelectedProduct() {
    const product = selectedProduct();
    document.getElementById('marca-producto').value = product?.marca || '';
    document.getElementById('procedencia-producto').value = product?.procedencia || '';
    document.getElementById('vencimiento-producto').value = '';
    const lotSelect = document.getElementById('lote-producto');
    lotSelect.replaceChildren(new Option('Sin lote seleccionado', ''));
    (product?.lotes || []).forEach(lote => lotSelect.add(new Option(`${lote.codigo || 'Sin código'}${lote.vencimiento ? ' - ' + lote.vencimiento : ''}`, lote.id)));
    if (! product) {
        ['codigo-producto', 'existencia-producto', 'costo-producto', 'precio-producto'].forEach(id => document.getElementById(id).value = '');
        return;
    }
    document.getElementById('codigo-producto').value = product.codigo || '';
    document.getElementById('existencia-producto').value = money(product.stock);
    document.getElementById('costo-producto').value = money(product.cost);
    document.getElementById('porcentaje-utilidad').value = money(product.margin || 27);
    document.getElementById('precio-producto').value = money(calculatedPrice(product));
}
function refreshQuotedPrice() {
    const product = selectedProduct();
    if (! product) return;
    document.getElementById('precio-producto').value = money(calculatedPrice(product));
}
function refreshFooterCompany() {
    const brand = document.querySelector('[name="nombre_comercial"]')?.value || 'San Benjamin';
    document.getElementById('footer-empresa').value = footerCompanies[brand] || footerCompanies['San Benjamin'];
}
function filterSelect(inputId, selectId, onChange, includeDataset) {
    filterSearchableSelect(document.getElementById(selectId), document.getElementById(inputId).value, onChange, includeDataset);
}
function addSelectedItem(saved = null) {
    const manual = saved ? !saved.producto_id && Boolean(Number(saved.por_pedido)) : isOrderMode();
    const product = manual ? {
        id: null, codigo: 'Por pedido', nombre: saved?.nombre_producto ?? document.getElementById('nombre-pedido').value.trim(),
        medida: saved?.medida ?? document.getElementById('medida-pedido').value.trim(), cost: 0, margin: 27,
    } : saved ? products.find(p => Number(p.id) === Number(saved.producto_id)) : selectedProduct();
    if (! product) return;
    if (manual && !product.nombre) {
        document.getElementById('nombre-pedido').setCustomValidity('Introduzca la descripción del artículo.');
        document.getElementById('nombre-pedido').reportValidity();
        return;
    }
    const tbody = document.querySelector('#items tbody');
    const tr = document.createElement('tr');
    const cantidad = Number(saved?.cantidad ?? document.getElementById('cantidad-producto').value ?? 1);
    const costo = Number(saved?.costo_referencial ?? product.cost ?? 0);
    const porcentaje = Number(saved?.porcentaje_utilidad ?? document.getElementById('porcentaje-utilidad').value ?? product.margin ?? 27);
    const precio = Number(saved?.precio_unitario ?? document.getElementById('precio-producto').value ?? 0);
    const descuento = saved?.descuento ?? (cantidad * precio * Number(document.getElementById('descuento-porcentaje').value || 0) / 100);
    tr.innerHTML = `
        <td>${escapeHtml(product.codigo)}${manual ? `<input type="hidden" name="items[${idx}][por_pedido]" value="1">` : `<input type="hidden" name="items[${idx}][producto_id]" value="${Number(product.id)}">`}${saved?.id ? `<input type="hidden" name="items[${idx}][id]" value="${Number(saved.id)}">` : ''}</td>
        <td>${manual ? `<label>Descripción<input required maxlength="255" name="items[${idx}][nombre_producto]" value="${escapeHtml(product.nombre)}"></label>
            <label>Vincular producto<select name="items[${idx}][producto_id]"><option value="">Pendiente de vincular</option>${products.map(p => `<option value="${Number(p.id)}">${escapeHtml(p.codigo + ' - ' + p.nombre)}</option>`).join('')}</select></label>` : escapeHtml(product.nombre)}
            <details><summary>Marca, procedencia y vencimiento</summary>
                <label>Marca<input maxlength="255" name="items[${idx}][marca]" value="${escapeHtml(saved ? saved.marca : document.getElementById('marca-producto').value)}"></label>
                <label>Procedencia<input maxlength="255" name="items[${idx}][procedencia]" value="${escapeHtml(saved ? saved.procedencia : document.getElementById('procedencia-producto').value)}"></label>
                <label>Vencimiento<input type="date" name="items[${idx}][fecha_vencimiento]" value="${escapeHtml(saved ? saved.fecha_vencimiento : document.getElementById('vencimiento-producto').value)}"></label>
                <input type="hidden" name="items[${idx}][inventario_lote_id]" value="${Number(saved ? saved.inventario_lote_id : document.getElementById('lote-producto').value) || ''}">
            </details>
        </td>
        <td>${manual ? `<input aria-label="Presentación" maxlength="100" name="items[${idx}][medida]" value="${escapeHtml(product.medida)}">` : escapeHtml(product.medida)}</td>
        <td><input class="item-cantidad" type="number" step="0.01" min="0.01" name="items[${idx}][cantidad]" value="${money(cantidad)}"></td>
        <td data-cost="${money(costo)}">${money(costo)}</td>
        <td><input class="item-porcentaje" type="number" step="0.01" min="25" max="30" name="items[${idx}][porcentaje_utilidad]" value="${money(porcentaje)}"></td>
        <td><input class="item-precio" type="number" step="0.01" min="0" name="items[${idx}][precio_unitario]" value="${money(precio)}"></td>
        <td><input class="item-descuento" type="number" step="0.01" min="0" name="items[${idx}][descuento]" value="${money(descuento)}"></td>
        <td class="item-subtotal">0.00</td>
        <td><input class="item-impuesto" type="number" step="0.01" min="0" name="items[${idx}][impuesto]" value="${money(saved?.impuesto ?? 0)}"></td>
        <td class="item-total">0.00</td>
        <td><button type="button" class="btn secondary" onclick="this.closest('tr').remove(); updateTotals();">Quitar</button></td>
    `;
    tbody.appendChild(tr);
    tr.querySelectorAll('input').forEach(input => input.addEventListener('input', () => updateRow(tr)));
    updateRow(tr);
    idx++;
}
function updateRow(tr, recalculateDiscount = false) {
    const cantidad = parseFloat(tr.querySelector('.item-cantidad').value || '0');
    const precioInput = tr.querySelector('.item-precio');
    const porcentajeInput = tr.querySelector('.item-porcentaje');
    const costo = parseFloat(tr.querySelector('[data-cost]')?.dataset.cost || '0');
    if (document.activeElement === porcentajeInput && costo > 0) {
        precioInput.value = money(costo * (1 + ((parseFloat(porcentajeInput.value || '27')) / 100)));
    }
    const precio = parseFloat(precioInput.value || '0');
    const porcentaje = parseFloat(document.getElementById('descuento-porcentaje').value || '0');
    const subtotal = cantidad * precio;
    const descuentoInput = tr.querySelector('.item-descuento');
    if (recalculateDiscount) descuentoInput.value = money(subtotal * (porcentaje / 100));
    const descuento = parseFloat(descuentoInput.value || '0');
    const impuesto = parseFloat(tr.querySelector('.item-impuesto').value || '0');
    tr.querySelector('.item-subtotal').textContent = money(subtotal);
    tr.querySelector('.item-total').textContent = money(Math.max(0, subtotal - descuento) + impuesto);
    updateTotals();
}
function updateTotals() {
    let subtotal = 0, descuento = 0, impuesto = 0, total = 0;
    document.querySelectorAll('#items tbody tr').forEach(tr => {
        subtotal += parseFloat(tr.querySelector('.item-subtotal').textContent || '0');
        descuento += parseFloat(tr.querySelector('.item-descuento').value || '0');
        impuesto += parseFloat(tr.querySelector('.item-impuesto').value || '0');
        total += parseFloat(tr.querySelector('.item-total').textContent || '0');
    });
    document.getElementById('total-descuento').textContent = money(descuento);
    document.getElementById('total-subtotal').textContent = money(subtotal);
    document.getElementById('total-impuesto').textContent = money(impuesto);
    document.getElementById('total-final').textContent = money(total);
}
document.getElementById('producto-selector').addEventListener('change', refreshSelectedProduct);
document.getElementById('nombre-pedido').addEventListener('input', function () { this.setCustomValidity(''); });
document.querySelectorAll('[name="modo_articulo"]').forEach(input => input.addEventListener('change', () => {
    const manual = isOrderMode();
    document.getElementById('articulo-catalogo').hidden = manual;
    document.getElementById('articulo-pedido').hidden = !manual;
    document.getElementById('lote-producto').disabled = manual;
    document.getElementById('nombre-pedido').setCustomValidity('');
    refreshSelectedProduct();
}));
document.getElementById('lote-producto').addEventListener('change', function () {
    const lote = selectedProduct()?.lotes.find(l => Number(l.id) === Number(this.value));
    document.getElementById('vencimiento-producto').value = lote?.vencimiento || '';
});
document.getElementById('quotation-form').addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && event.target instanceof HTMLInputElement && !event.isComposing) {
        event.preventDefault();
        if (['cantidad-producto', 'precio-producto', 'marca-producto', 'procedencia-producto', 'vencimiento-producto'].includes(event.target.id)) addSelectedItem();
    }
});
document.getElementById('lista-precios').addEventListener('change', refreshSelectedProduct);
document.querySelector('[name="nombre_comercial"]').addEventListener('change', refreshFooterCompany);
document.getElementById('porcentaje-utilidad').addEventListener('input', refreshQuotedPrice);
document.getElementById('buscar-producto').addEventListener('input', () => filterSelect('buscar-producto', 'producto-selector', refreshSelectedProduct, false));
document.getElementById('cliente-id').addEventListener('change', function () { document.getElementById('nit-cliente').value = this.selectedOptions[0]?.dataset.documento || ''; });
document.getElementById('buscar-cliente').addEventListener('input', () => filterSelect('buscar-cliente', 'cliente-id', () => {
    const select = document.getElementById('cliente-id');
    document.getElementById('nit-cliente').value = select.selectedOptions[0]?.dataset.documento || '';
}, true));
document.getElementById('descuento-porcentaje').addEventListener('input', function () { document.querySelectorAll('#items tbody tr').forEach(tr => updateRow(tr, true)); });
document.getElementById('quotation-form').addEventListener('submit', function (event) {
    if (document.querySelectorAll('#items tbody tr').length === 0) {
        event.preventDefault();
        document.getElementById('cotizacion-items-alert').classList.add('is-open');
    }
});
refreshSelectedProduct();
refreshFooterCompany();
Object.values(initialItems).forEach(item => addSelectedItem(item));
</script>
@endsection
