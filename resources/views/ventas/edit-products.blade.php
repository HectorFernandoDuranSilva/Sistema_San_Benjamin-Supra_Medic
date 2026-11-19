@extends('layouts.app')

@section('content')
<div class="top">
    <div>
        <h1>Editar {{ $venta->tipo_documento === 'nota_entrega' ? 'nota de entrega' : 'venta' }} {{ $venta->numero_venta }}</h1>
        <p class="muted">Actualice los datos, productos, lotes y totales desde un solo lugar.</p>
    </div>
    <a class="btn secondary" href="{{ route($venta->tipo_documento === 'nota_entrega' ? 'notas.show' : 'ventas.show', $venta) }}">Volver</a>
</div>

@if($errors->any())<div class="notice danger">{{ $errors->first() }}</div>@endif
@if(!$puedeEditarProductos)
    <div class="notice">Puede actualizar únicamente la cabecera. Para modificar productos, primero regularice los cobros y cuotas del documento.</div>
@else
    <div class="notice">El modo de stock original se conserva para proteger el inventario. Los cambios de productos, lotes y totales quedan registrados en auditoría.</div>
@endif

<form id="sale-document-editor" method="post" action="{{ route('ventas.historial.update', $venta) }}">
    @csrf @method('put')
    <section class="card">
        <div class="actions" style="justify-content:space-between;margin-bottom:12px;"><strong>Datos del documento</strong></div>
        <div class="row">
            <div class="field"><label>Nombre comercial</label><select name="nombre_comercial"><option value="San Benjamin" @selected(old('nombre_comercial', $venta->nombre_comercial) === 'San Benjamin')>San Benjamin</option><option value="Supra Medic" @selected(old('nombre_comercial', $venta->nombre_comercial) === 'Supra Medic')>Supra Medic</option></select></div>
            <div class="field"><label>Tipo documento</label><select name="tipo_documento"><option value="factura" @selected(old('tipo_documento', $venta->tipo_documento) === 'factura')>Factura</option><option value="nota_entrega" @selected(old('tipo_documento', $venta->tipo_documento) === 'nota_entrega')>Nota de entrega</option></select></div>
            <div class="field"><label>Fecha</label><input type="date" name="fecha" value="{{ old('fecha', $venta->fecha->format('Y-m-d')) }}" required></div>
            <div class="field"><label>Factura o referencia</label><input name="factura" value="{{ old('factura', $venta->factura) }}"></div>
            <div class="field"><label>NIT</label><input name="nit_cliente" value="{{ old('nit_cliente', $venta->nit_cliente) }}"></div>
        </div>
        <div class="row">
            <div class="field"><label>Cliente</label><select name="cliente_id"><option value="">Sin cliente</option>@foreach($clientes as $cliente)<option value="{{ $cliente->id }}" @selected(old('cliente_id', $venta->cliente_id)==$cliente->id)>{{ $cliente->nombre_mostrable }}</option>@endforeach</select></div>
            <div class="field"><label>Lista de precios</label><select id="lista-precios" name="lista_precios"><option value="Precio de Venta" @selected(old('lista_precios', $venta->lista_precios) === 'Precio de Venta')>Precio de Venta</option><option value="Precio Sugerido" @selected(old('lista_precios', $venta->lista_precios) === 'Precio Sugerido')>Precio sugerido por reposición</option><option value="Costo Referencial" @selected(old('lista_precios', $venta->lista_precios) === 'Costo Referencial')>Costo referencial</option></select></div>
            <div class="field"><label>Descuento %</label><input id="descuento-porcentaje" type="number" name="descuento_porcentaje" min="0" max="100" step="0.01" value="{{ old('descuento_porcentaje', $venta->descuento_porcentaje) }}"></div>
            <div class="field"><label>Marca tarjeta</label><select name="tipo_tarjeta"><option value="">-</option><option value="genrui" @selected(old('tipo_tarjeta', $venta->tipo_tarjeta) === 'genrui')>Genrui</option><option value="getein" @selected(old('tipo_tarjeta', $venta->tipo_tarjeta) === 'getein')>Getein</option></select></div>
            <div class="field"><label>Destinatario tarjeta</label><input name="destinatario_tarjeta" maxlength="255" value="{{ old('destinatario_tarjeta', $venta->destinatario_tarjeta) }}"></div>
        </div>
        <div class="field"><label>Comentario</label><textarea name="observacion">{{ old('observacion', $venta->observacion) }}</textarea></div>
    </section>

    <section class="card">
        <div class="actions" style="justify-content:space-between;margin-bottom:12px;"><strong>Productos del documento</strong></div>
        <div class="row" style="margin-bottom:16px;">
            <div class="field"><label for="edit-sale-product-search">Buscar producto</label><input id="edit-sale-product-search" type="search" placeholder="Código o nombre" autocomplete="off" @disabled(!$puedeEditarProductos)></div>
            <div class="field"><label for="edit-sale-product-selector">Producto</label><select id="edit-sale-product-selector" @disabled(!$puedeEditarProductos)></select></div>
            <div class="field" style="justify-content:end;"><label>&nbsp;</label><button type="button" class="btn secondary" id="add-sale-line" @disabled(!$puedeEditarProductos)>Agregar producto</button></div>
        </div>
        <div style="overflow-x:auto;">
            <table id="sale-lines" style="min-width:1500px;">
                <thead><tr><th>Producto</th><th>Cantidad</th><th>Precio Bs</th><th>Desc. Bs</th><th>Imp. Bs</th><th>Envío Bs</th><th>Otros Bs</th><th>Lote existente</th><th>Lote manual</th><th>Vencimiento manual</th><th>C/T</th><th>Acción</th></tr></thead>
                <tbody></tbody>
                <tfoot><tr><th colspan="2" style="text-align:right;">Totales</th><th id="sale-total-subtotal">Bs 0.00</th><th id="sale-total-discount">Bs 0.00</th><th id="sale-total-tax">Bs 0.00</th><th id="sale-total-shipping">Bs 0.00</th><th id="sale-total-other">Bs 0.00</th><th colspan="4"></th><th id="sale-total-final">Bs 0.00</th></tr></tfoot>
            </table>
        </div>
    </section>
    <div class="actions" style="margin-top:16px;"><button class="btn">Guardar documento</button></div>
</form>

<script src="{{ asset('js/searchable-select.js') }}"></script>
<script>
const saleProducts = @json($productosEditor);
const saleInitialItems = @json(old('items', $itemsEditor));
const saleCanEditProducts = @json($puedeEditarProductos);
let saleLineIndex = 0;

function saleEsc(value) { const span = document.createElement('span'); span.textContent = String(value ?? ''); return span.innerHTML.replaceAll('"', '&quot;').replaceAll("'", '&#39;'); }
function saleMoney(value) { return (Number(value) || 0).toFixed(2); }
function saleCurrency(value) { return `Bs ${saleMoney(value)}`; }
function saleProduct(id) { return saleProducts.find(item => Number(item.id) === Number(id)); }
function saleProductOptions(selected) { return saleProducts.map(product => `<option value="${product.id}" ${Number(selected) === Number(product.id) ? 'selected' : ''}>${saleEsc(product.codigo)} - ${saleEsc(product.nombre)}</option>`).join(''); }
function refreshSaleProductPicker() {
    const picker = document.getElementById('edit-sale-product-selector');
    const previous = picker.value;
    picker.innerHTML = saleProductOptions(previous || saleProducts[0]?.id);
}
function saleLotOptions(product, selected) {
    const lots = product?.lotes || [];
    return `<option value="">Sistema / otros lotes</option>${lots.map(lot => { const expiration = lot.vencimiento ? ` - ${lot.vencimiento}` : ' - sin vencimiento'; return `<option value="${lot.id}" ${Number(selected) === Number(lot.id) ? 'selected' : ''}>${saleEsc(lot.codigo)}${expiration} - disponible ${saleMoney(lot.disponible)}</option>`; }).join('')}`;
}
function saleDefaultPrice(product) {
    const list = document.getElementById('lista-precios').value;
    if (list === 'Costo Referencial') return Number(product?.costo_referencial || 0);
    if (list === 'Precio Sugerido') return Number(product?.precio_sugerido || product?.precio_venta || 0);
    return Number(product?.precio_venta || 0);
}
function syncSaleLots(row, selectedLot = null) {
    const product = saleProduct(row.querySelector('.sale-product').value);
    const lotSelect = row.querySelector('.sale-lot');
    lotSelect.innerHTML = saleLotOptions(product, selectedLot ?? lotSelect.value);
    lotSelect.disabled = !saleCanEditProducts || !product || (product.lotes || []).length === 0;
}
function updateSaleLine(row, refreshDiscount = false) {
    const quantity = Number(row.querySelector('.sale-quantity').value || 0);
    const price = Number(row.querySelector('.sale-price').value || 0);
    const subtotal = quantity * price;
    const discount = row.querySelector('.sale-discount');
    if (refreshDiscount || document.activeElement !== discount) discount.value = saleMoney(subtotal * (Number(document.getElementById('descuento-porcentaje').value || 0) / 100));
    updateSaleTotals();
}
function updateSaleTotals() {
    const totals = { subtotal: 0, discount: 0, tax: 0, shipping: 0, other: 0 };
    document.querySelectorAll('#sale-lines tbody tr').forEach(row => {
        totals.subtotal += Number(row.querySelector('.sale-quantity').value || 0) * Number(row.querySelector('.sale-price').value || 0);
        totals.discount += Number(row.querySelector('.sale-discount').value || 0);
        totals.tax += Number(row.querySelector('.sale-tax').value || 0);
        totals.shipping += Number(row.querySelector('.sale-shipping').value || 0);
        totals.other += Number(row.querySelector('.sale-other').value || 0);
    });
    const final = Math.max(0, totals.subtotal - totals.discount) + totals.tax + totals.shipping + totals.other;
    document.getElementById('sale-total-subtotal').textContent = saleCurrency(totals.subtotal);
    document.getElementById('sale-total-discount').textContent = saleCurrency(totals.discount);
    document.getElementById('sale-total-tax').textContent = saleCurrency(totals.tax);
    document.getElementById('sale-total-shipping').textContent = saleCurrency(totals.shipping);
    document.getElementById('sale-total-other').textContent = saleCurrency(totals.other);
    document.getElementById('sale-total-final').textContent = saleCurrency(final);
}
function addSaleLine(item = {}) {
    const tbody = document.querySelector('#sale-lines tbody');
    const row = document.createElement('tr');
    const productId = item.producto_id ?? saleProducts[0]?.id ?? '';
    const product = saleProduct(productId);
    const disabled = saleCanEditProducts ? '' : 'disabled';
    const price = item.precio_unitario ?? saleDefaultPrice(product);
    row.innerHTML = `
        <td><select class="sale-product" name="items[${saleLineIndex}][producto_id]" required ${disabled}>${saleProductOptions(productId)}</select></td>
        <td><input class="sale-quantity" type="number" name="items[${saleLineIndex}][cantidad]" min="0.01" step="0.01" value="${saleMoney(item.cantidad ?? 1)}" required ${disabled}></td>
        <td><input class="sale-price" type="number" name="items[${saleLineIndex}][precio_unitario]" min="0" step="0.01" value="${saleMoney(price)}" required ${disabled}></td>
        <td><input class="sale-discount" type="number" name="items[${saleLineIndex}][descuento]" min="0" step="0.01" value="${saleMoney(item.descuento)}" ${disabled}></td>
        <td><input class="sale-tax" type="number" name="items[${saleLineIndex}][impuesto]" min="0" step="0.01" value="${saleMoney(item.impuesto)}" ${disabled}></td>
        <td><input class="sale-shipping" type="number" name="items[${saleLineIndex}][costo_envio]" min="0" step="0.01" value="${saleMoney(item.costo_envio)}" ${disabled}></td>
        <td><input class="sale-other" type="number" name="items[${saleLineIndex}][otros_costos]" min="0" step="0.01" value="${saleMoney(item.otros_costos)}" ${disabled}></td>
        <td><select class="sale-lot" name="items[${saleLineIndex}][inventario_lote_id]" ${disabled}><option value="">Sistema / otros lotes</option></select><small class="muted">El sistema completa con lotes vigentes.</small></td>
        <td><input name="items[${saleLineIndex}][codigo_lote_manual]" value="${saleEsc(item.codigo_lote_manual)}" placeholder="Solo si corresponde" ${disabled}></td>
        <td><input type="date" name="items[${saleLineIndex}][fecha_vencimiento_manual]" value="${saleEsc(item.fecha_vencimiento_manual)}" ${disabled}></td>
        <td><input type="hidden" name="items[${saleLineIndex}][tarjeta_magnetica]" value="0" ${disabled}><input type="checkbox" name="items[${saleLineIndex}][tarjeta_magnetica]" value="1" ${item.tarjeta_magnetica ? 'checked' : ''} ${disabled}></td>
        <td><button type="button" class="btn secondary remove-sale-line" ${disabled}>Quitar</button></td>`;
    tbody.appendChild(row);
    syncSaleLots(row, item.inventario_lote_id);
    row.querySelector('.sale-product').addEventListener('change', function () { const selected = saleProduct(this.value); row.querySelector('.sale-price').value = saleMoney(saleDefaultPrice(selected)); syncSaleLots(row); updateSaleLine(row, true); });
    row.querySelectorAll('.sale-quantity, .sale-price, .sale-discount, .sale-tax, .sale-shipping, .sale-other').forEach(input => input.addEventListener('input', () => updateSaleLine(row)));
    row.querySelector('.remove-sale-line').addEventListener('click', () => { if (document.querySelectorAll('#sale-lines tbody tr').length <= 1) { row.querySelector('.sale-product').setCustomValidity('Debe conservar al menos un producto.'); row.querySelector('.sale-product').reportValidity(); return; } row.remove(); updateSaleTotals(); });
    saleLineIndex++;
    updateSaleLine(row);
}
refreshSaleProductPicker();
document.getElementById('edit-sale-product-search').addEventListener('input', function () {
    filterSearchableSelect(document.getElementById('edit-sale-product-selector'), this.value, () => {});
});
document.getElementById('add-sale-line').addEventListener('click', () => {
    const picker = document.getElementById('edit-sale-product-selector');
    if (!picker.value) {
        picker.setCustomValidity('Busque y seleccione un producto para agregarlo.');
        picker.reportValidity();
        return;
    }
    picker.setCustomValidity('');
    addSaleLine({ producto_id: picker.value });
});
document.getElementById('descuento-porcentaje').addEventListener('input', () => document.querySelectorAll('#sale-lines tbody tr').forEach(row => updateSaleLine(row, true)));
saleInitialItems.forEach(addSaleLine);
if (!saleInitialItems.length) addSaleLine();
</script>
@endsection
