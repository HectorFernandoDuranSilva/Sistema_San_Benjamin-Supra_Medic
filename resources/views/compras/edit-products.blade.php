@extends('layouts.app')

@section('content')
<div class="top">
    <div>
        <h1>Editar compra {{ $compra->numero_compra }}</h1>
        <p class="muted">En esta pantalla puede modificar la cabecera, agregar o quitar productos y ajustar sus lotes.</p>
    </div>
    <a class="btn secondary" href="{{ route('compras.show', $compra) }}">Volver</a>
</div>
@if($errors->any())<div class="notice danger">{{ $errors->first() }}</div>@endif
<div class="notice">Las compras con pagos registrados o lotes ya consumidos no se pueden modificar para proteger la trazabilidad.</div>

<form class="card" method="post" action="{{ route('compras.historial.update', $compra) }}">
    @csrf @method('put')
    <div class="actions" style="justify-content:space-between;margin-bottom:12px;"><strong>Datos de cabecera</strong><button class="btn">Guardar cabecera</button></div>
    <div class="row">
        <div class="field"><label>Nombre comercial</label><select name="nombre_comercial"><option value="San Benjamin" @selected(old('nombre_comercial', $compra->nombre_comercial) === 'San Benjamin')>San Benjamin</option><option value="Supra Medic" @selected(old('nombre_comercial', $compra->nombre_comercial) === 'Supra Medic')>Supra Medic</option></select></div>
        <div class="field"><label>Tipo documento</label><select name="tipo_documento"><option value="factura" @selected(old('tipo_documento', $compra->tipo_documento) === 'factura')>Factura</option><option value="nota_entrega" @selected(old('tipo_documento', $compra->tipo_documento) === 'nota_entrega')>Nota de entrega</option></select></div>
        <div class="field"><label>Fecha</label><input type="date" name="fecha" value="{{ old('fecha', $compra->fecha->format('Y-m-d')) }}" required></div>
        <div class="field"><label>Factura</label><input name="factura" value="{{ old('factura', $compra->factura) }}"></div>
        <div class="field"><label>NIT</label><input name="nit_proveedor" value="{{ old('nit_proveedor', $compra->nit_proveedor) }}"></div>
    </div>
    <div class="field"><label>Proveedor</label><select name="proveedor_id"><option value="">Sin proveedor</option>@foreach($proveedores as $proveedor)<option value="{{ $proveedor->id }}" @selected(old('proveedor_id', $compra->proveedor_id)==$proveedor->id)>{{ $proveedor->nombre }}</option>@endforeach</select></div>
    <div class="field"><label>Comentario</label><textarea name="observacion">{{ old('observacion', $compra->observacion) }}</textarea></div>
</form>

<form id="purchase-product-editor" class="card" method="post" action="{{ route('compras.productos.update', $compra) }}">
    @csrf @method('put')
    <div class="actions" style="justify-content:space-between;margin-bottom:12px;">
        <strong>Productos de la compra</strong>
    </div>
    <div class="row" style="margin-bottom:16px;">
        <div class="field"><label for="edit-purchase-product-search">Buscar producto</label><input id="edit-purchase-product-search" type="search" placeholder="Código o nombre" autocomplete="off" @disabled(!$puedeEditarProductos)></div>
        <div class="field"><label for="edit-purchase-product-selector">Producto</label><select id="edit-purchase-product-selector" @disabled(!$puedeEditarProductos)></select></div>
        <div class="field" style="align-self:end;"><label>&nbsp;</label><button type="button" class="btn secondary" id="add-purchase-line" @disabled(!$puedeEditarProductos)>Agregar producto</button></div>
    </div>
    <div style="overflow-x:auto;">
        <table id="purchase-lines" style="min-width:1450px;">
            <thead><tr><th>Producto</th><th>Cantidad</th><th>Costo Bs</th><th>Utilidad %</th><th>Descuento fijo Bs</th><th>Impuesto Bs</th><th>Tipo de lote</th><th>Lote</th><th>Vencimiento</th><th>Acción</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
    <div class="actions" style="margin-top:16px;"><button class="btn" @disabled(!$puedeEditarProductos)>Guardar productos, lotes e inventario</button></div>
</form>

<script src="{{ asset('js/searchable-select.js') }}"></script>
<script>
const purchaseProducts = @json($productosEditor);
const purchaseInitialItems = @json(old('items', $itemsEditor));
let purchaseLineIndex = 0;
function purchaseEsc(value) { const span = document.createElement('span'); span.textContent = String(value ?? ''); return span.innerHTML.replaceAll('"', '&quot;').replaceAll("'", '&#39;'); }
function purchaseMoney(value) { return (Number(value) || 0).toFixed(2); }
function purchaseProductOptions(selected) { return purchaseProducts.map(product => `<option value="${product.id}" ${Number(selected) === Number(product.id) ? 'selected' : ''}>${purchaseEsc(product.codigo)} - ${purchaseEsc(product.nombre)}</option>`).join(''); }
function addPurchaseLine(item = {}) {
    const tbody = document.querySelector('#purchase-lines tbody');
    const row = document.createElement('tr');
    const productId = item.producto_id ?? purchaseProducts[0]?.id ?? '';
    row.innerHTML = `
        <td><select class="purchase-product" name="items[${purchaseLineIndex}][producto_id]" required>${purchaseProductOptions(productId)}</select></td>
        <td><input type="number" name="items[${purchaseLineIndex}][cantidad]" min="0" step="0.01" value="${purchaseMoney(item.cantidad)}" required></td>
        <td><input type="number" name="items[${purchaseLineIndex}][costo_unitario]" min="0" step="0.01" value="${purchaseMoney(item.costo_unitario)}" required></td>
        <td><input type="number" name="items[${purchaseLineIndex}][porcentaje_utilidad]" min="25" max="30" step="0.01" value="${purchaseMoney(item.porcentaje_utilidad ?? 27)}" required></td>
        <td><input type="number" name="items[${purchaseLineIndex}][descuento_fijo]" min="0" step="0.01" value="${purchaseMoney(item.descuento_fijo)}"></td>
        <td><input type="number" name="items[${purchaseLineIndex}][impuesto]" min="0" step="0.01" value="${purchaseMoney(item.impuesto)}"></td>
        <td><select name="items[${purchaseLineIndex}][tipo_lote]"><option value="con_lote" ${item.tipo_lote === 'con_lote' ? 'selected' : ''}>Con lote</option><option value="sin_vencimiento" ${item.tipo_lote === 'sin_vencimiento' ? 'selected' : ''}>Sin vencimiento</option><option value="sin_lote" ${item.tipo_lote === 'sin_lote' ? 'selected' : ''}>Sin lote</option></select></td>
        <td><input name="items[${purchaseLineIndex}][codigo_lote]" value="${purchaseEsc(item.codigo_lote)}" placeholder="Número o código"></td>
        <td><input type="date" name="items[${purchaseLineIndex}][fecha_vencimiento]" value="${purchaseEsc(item.fecha_vencimiento)}"></td>
        <td><button type="button" class="btn secondary remove-purchase-line">Quitar</button></td>`;
    tbody.appendChild(row);
    row.querySelector('.remove-purchase-line').addEventListener('click', () => {
        if (document.querySelectorAll('#purchase-lines tbody tr').length <= 1) { row.querySelector('select').setCustomValidity('Debe conservar al menos un producto.'); row.querySelector('select').reportValidity(); return; }
        row.remove();
    });
    purchaseLineIndex++;
}
function refreshPurchaseProductPicker() {
    const picker = document.getElementById('edit-purchase-product-selector');
    const previous = picker.value;
    picker.innerHTML = purchaseProductOptions(previous || purchaseProducts[0]?.id);
}
refreshPurchaseProductPicker();
document.getElementById('edit-purchase-product-search').addEventListener('input', function () {
    filterSearchableSelect(document.getElementById('edit-purchase-product-selector'), this.value, () => {});
});
document.getElementById('add-purchase-line').addEventListener('click', () => {
    const picker = document.getElementById('edit-purchase-product-selector');
    if (!picker.value) {
        picker.setCustomValidity('Busque y seleccione un producto para agregarlo.');
        picker.reportValidity();
        return;
    }
    picker.setCustomValidity('');
    addPurchaseLine({ producto_id: picker.value });
});
purchaseInitialItems.forEach(addPurchaseLine);
if (!purchaseInitialItems.length) addPurchaseLine();
</script>
@endsection
