@extends('layouts.app')

@section('content')
@php
    $productOptions = $productos->map(fn ($p) => [
        'id' => $p->id,
        'codigo' => $p->codigo,
        'nombre' => $p->nombre,
        'marca' => $p->marca,
        'procedencia' => $p->procedencia,
        'cost' => $p->costo_referencial,
        'margin' => $p->porcentaje_utilidad,
    ])->values();
@endphp
<div class="top"><h1>Registro de compras</h1></div>
<form class="card" method="post" action="{{ route('compras.store') }}">
@csrf
<div class="row">
    <div class="field">
        <label>Nombre comercial</label>
        <select name="nombre_comercial">
            <option value="San Benjamin" @selected(old('nombre_comercial', $commercial['name'] ?? '') === 'San Benjamin')>San Benjamin</option>
            <option value="Supra Medic" @selected(old('nombre_comercial', $commercial['name'] ?? '') === 'Supra Medic')>Supra Medic</option>
        </select>
    </div>
    <div class="field">
        <label>Tipo documento</label>
        <select name="tipo_documento">
            <option value="factura" @selected(old('tipo_documento', 'factura') === 'factura')>Factura</option>
            <option value="nota_entrega" @selected(old('tipo_documento') === 'nota_entrega')>Nota de entrega</option>
        </select>
    </div>
    <div class="field"><label>Fecha</label><input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" required></div>
    <div class="field"><label>Factura</label><input name="factura" value="{{ old('factura') }}"></div>
    <div class="field"><label>No.</label><input name="numero_compra" value="{{ old('numero_compra') }}" placeholder="Automatico"></div>
</div>

<section id="plan-pago-compra" style="margin:16px 0;padding:14px 0;border-top:1px solid var(--line);border-bottom:1px solid var(--line);">
    <div class="row">
        <div class="field"><label>Forma de pago</label><select id="plan-pago-compra-tipo" name="plan_pago"><option value="contado" @selected(old('plan_pago', 'contado') === 'contado')>Pago único</option><option value="cuotas" @selected(old('plan_pago') === 'cuotas')>Pago en cuotas</option></select></div>
        <div class="field cuotas-compra-control" hidden><label>Número de cuotas</label><input id="cantidad-cuotas-compra" type="number" min="2" max="120" value="2"></div>
        <div class="field cuotas-compra-control" hidden><label>Primer vencimiento</label><input id="primer-vencimiento-cuota-compra" type="date" value="{{ now()->addMonth()->toDateString() }}"></div>
        <div class="field cuotas-compra-control" style="justify-content:end;" hidden><button type="button" id="generar-cuotas-compra" class="btn secondary">Generar cuotas</button></div>
    </div>
    <div id="calendario-cuotas-compra" hidden><p>La suma de las cuotas debe coincidir con el total de la compra.</p><table><thead><tr><th>Cuota</th><th>Vencimiento</th><th>Importe Bs</th><th>Detalle</th></tr></thead><tbody id="cuotas-compra-body"></tbody></table><p id="resumen-cuotas-compra"></p></div>
</section>

<div class="row">
    <div class="field"><label>NIT</label><input id="nit-proveedor" name="nit_proveedor" value="{{ old('nit_proveedor') }}"></div>
    <div class="field">
        <label>Buscar proveedor</label>
        <input id="buscar-proveedor" placeholder="Nombre o NIT">
    </div>
    <div class="field">
        <label>Proveedor</label>
        <select id="proveedor-id" name="proveedor_id">
            <option value="">Sin proveedor</option>
            @foreach($proveedores as $p)
                <option value="{{ $p->id }}" data-documento="{{ $p->documento }}" @selected(old('proveedor_id')==$p->id)>{{ $p->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="field"><label>Comentario</label><input name="observacion" value="{{ old('observacion') }}"></div>
    <div class="field"><label>Utilidad %</label><input id="porcentaje-utilidad" type="number" step="0.01" min="25" max="30" value="27"></div>
    <div class="field"><label>Descuento proveedor %</label><input id="descuento-producto" type="number" step="0.01" min="0" max="100" value="0"></div>
</div>

<div class="row">
    <div class="field"><label>Código</label><input id="codigo-producto" readonly></div>
    <div class="field">
        <label>Buscar producto</label>
        <input id="buscar-producto" placeholder="Código o nombre">
    </div>
    <div class="field">
        <label>Producto</label>
        <select id="producto-selector">
            @foreach($productos as $p)
                <option value="{{ $p->id }}">{{ $p->codigo }} - {{ $p->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="field"><label>Marca</label><input id="marca-producto" readonly></div>
    <div class="field"><label>Procedencia</label><input id="procedencia-producto" readonly></div>
    <div class="field"><label>Costo Unit</label><input id="costo-producto" type="number" step="0.01" min="0" value="0"></div>
    <div class="field"><label>Cantidad</label><input id="cantidad-producto" type="number" step="0.01" min="0.01" value="1"></div>
    <div class="field"><label>Lote</label><input id="lote-producto" maxlength="255" placeholder="Número de lote"></div>
    <div class="field"><label>Vencimiento</label><input id="vencimiento-producto" type="date"></div>
    <div class="field"><label>Precio sugerido</label><input id="precio-sugerido-producto" readonly value="0.00"></div>
</div>

<div class="actions" style="margin-bottom:12px;">
    <button type="button" class="btn secondary" onclick="addSelectedItem()">Agregar producto</button>
    <button class="btn">Registrar compra</button>
</div>

<div class="card" style="padding:0;">
    <table id="items">
        <thead>
            <tr>
                <th>Código</th><th>Producto</th><th>Marca</th><th>Procedencia</th><th>Cantidad</th><th>Costo Unit</th><th>Utilidad %</th><th>Desc. %</th><th>Lote</th><th>Vencimiento</th><th>Subtotal</th><th>Descuento</th><th>Impuesto</th><th>Total</th><th></th>
            </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
            <tr>
                <th colspan="10" style="text-align:right;">Totales</th>
                <th id="total-subtotal">0.00</th>
                <th id="total-descuento">0.00</th>
                <th id="total-impuesto">0.00</th>
                <th id="total-final">0.00</th>
                <th></th>
            </tr>
        </tfoot>
    </table>
</div>
</form>

<div id="compra-items-alert" class="glass-modal-backdrop" role="dialog" aria-modal="true">
    <div class="glass-modal is-error">
        <h2>Revise la compra</h2>
        <p>Agregue al menos un producto para registrar la compra.</p>
        <div class="glass-modal-actions">
            <button type="button" class="btn" data-glass-close>Entendido</button>
        </div>
    </div>
</div>

<script>
const products = @json($productOptions);
let idx = 0;

function money(value) {
    return (Number(value) || 0).toFixed(2);
}

function selectedProduct() {
    const id = Number(document.getElementById('producto-selector').value);
    return products.find(product => Number(product.id) === id) || products[0];
}

function updateSuggestedPrice() {
    const costo = parseFloat(document.getElementById('costo-producto').value || '0');
    const porcentaje = parseFloat(document.getElementById('porcentaje-utilidad').value || '27');
    document.getElementById('precio-sugerido-producto').value = money(costo * (1 + (porcentaje / 100)));
}

function refreshSelectedProduct() {
    const product = selectedProduct();
    if (! product) {
        return;
    }

    document.getElementById('codigo-producto').value = product.codigo || '';
    document.getElementById('marca-producto').value = product.marca || '-';
    document.getElementById('procedencia-producto').value = product.procedencia || '-';
    document.getElementById('costo-producto').value = money(product.cost);
    document.getElementById('porcentaje-utilidad').value = money(product.margin || 27);
    updateSuggestedPrice();
}

function filterProducts() {
    const term = document.getElementById('buscar-producto').value.toLowerCase().trim();
    const select = document.getElementById('producto-selector');
    let firstVisible = null;

    Array.from(select.options).forEach(option => {
        const visible = !term || option.text.toLowerCase().includes(term);
        option.hidden = !visible;

        if (visible && firstVisible === null) {
            firstVisible = option.value;
        }
    });

    if (firstVisible !== null && select.selectedOptions[0]?.hidden) {
        select.value = firstVisible;
        refreshSelectedProduct();
    }
}

function filterProviders() {
    const term = document.getElementById('buscar-proveedor').value.toLowerCase().trim();
    const select = document.getElementById('proveedor-id');
    let firstVisible = null;

    Array.from(select.options).forEach(option => {
        const documento = option.dataset.documento || '';
        const visible = !term || option.text.toLowerCase().includes(term) || documento.toLowerCase().includes(term);
        option.hidden = !visible;

        if (visible && firstVisible === null) {
            firstVisible = option.value;
        }
    });

    if (firstVisible !== null && select.selectedOptions[0]?.hidden) {
        select.value = firstVisible;
        document.getElementById('nit-proveedor').value = select.selectedOptions[0]?.dataset.documento || '';
    }
}

function addSelectedItem() {
    const product = selectedProduct();
    if (! product) {
        return;
    }

    const tbody = document.querySelector('#items tbody');
    const tr = document.createElement('tr');
    const cantidad = parseFloat(document.getElementById('cantidad-producto').value || '1');
    const costo = parseFloat(document.getElementById('costo-producto').value || product.cost || '0');
    const porcentaje = parseFloat(document.getElementById('porcentaje-utilidad').value || product.margin || '27');
    const descuento = parseFloat(document.getElementById('descuento-producto').value || '0');
    const lote = document.getElementById('lote-producto').value || '';
    const vencimiento = normalizeDateValue(document.getElementById('vencimiento-producto').value || '');

    tr.innerHTML = `
        <td>${product.codigo}<input type="hidden" name="items[${idx}][producto_id]" value="${product.id}"></td>
        <td>${product.nombre}</td>
        <td>${product.marca || '-'}</td>
        <td>${product.procedencia || '-'}</td>
        <td><input class="item-cantidad" type="number" step="0.01" min="0.01" name="items[${idx}][cantidad]" value="${money(cantidad)}"></td>
        <td><input class="item-costo" type="number" step="0.01" min="0" name="items[${idx}][costo_unitario]" value="${money(costo)}"></td>
        <td><input class="item-porcentaje" type="number" step="0.01" min="25" max="30" name="items[${idx}][porcentaje_utilidad]" value="${money(porcentaje)}"></td>
        <td><input class="item-descuento-porcentaje" type="number" step="0.01" min="0" max="100" name="items[${idx}][descuento_porcentaje]" value="${money(descuento)}"></td>
        <td><input class="item-lote" name="items[${idx}][codigo_lote]" value="${lote.replace(/"/g, '&quot;')}"></td>
        <td><input class="item-vencimiento" type="date" name="items[${idx}][fecha_vencimiento]" value="${vencimiento}"></td>
        <td class="item-subtotal">0.00</td>
        <td class="item-descuento">0.00</td>
        <td><input class="item-impuesto" type="number" step="0.01" min="0" name="items[${idx}][impuesto]" value="0.00"></td>
        <td class="item-total">0.00</td>
        <td><button type="button" class="btn secondary" onclick="this.closest('tr').remove(); updateTotals();">Quitar</button></td>
    `;

    tbody.appendChild(tr);
    tr.querySelectorAll('input').forEach(input => input.addEventListener('input', () => updateRow(tr)));
    updateRow(tr);
    idx++;
    document.getElementById('lote-producto').value = '';
    document.getElementById('vencimiento-producto').value = '';
}

function normalizeDateValue(value) {
    const trimmed = String(value || '').trim();
    const match = trimmed.match(/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/);

    if (! match) {
        return trimmed;
    }

    return `${match[3]}-${match[2].padStart(2, '0')}-${match[1].padStart(2, '0')}`;
}

function updateRow(tr) {
    const cantidad = parseFloat(tr.querySelector('.item-cantidad').value || '0');
    const costo = parseFloat(tr.querySelector('.item-costo').value || '0');
    const descuentoPorcentaje = parseFloat(tr.querySelector('.item-descuento-porcentaje').value || '0');
    const impuesto = parseFloat(tr.querySelector('.item-impuesto').value || '0');
    const subtotal = cantidad * costo;
    const descuento = subtotal * (descuentoPorcentaje / 100);
    const total = subtotal - descuento + impuesto;

    tr.querySelector('.item-subtotal').textContent = money(subtotal);
    tr.querySelector('.item-descuento').textContent = money(descuento);
    tr.querySelector('.item-total').textContent = money(total);
    updateTotals();
}

function updateTotals() {
    let subtotal = 0;
    let descuento = 0;
    let impuesto = 0;
    let total = 0;

    document.querySelectorAll('#items tbody tr').forEach(tr => {
        subtotal += parseFloat(tr.querySelector('.item-subtotal').textContent || '0');
        descuento += parseFloat(tr.querySelector('.item-descuento').textContent || '0');
        impuesto += parseFloat(tr.querySelector('.item-impuesto').value || '0');
        total += parseFloat(tr.querySelector('.item-total').textContent || '0');
    });

    document.getElementById('total-subtotal').textContent = money(subtotal);
    document.getElementById('total-descuento').textContent = money(descuento);
    document.getElementById('total-impuesto').textContent = money(impuesto);
    document.getElementById('total-final').textContent = money(total);
    updateInstallmentSummary();
}

function generatePurchaseInstallments() {
    const count = Math.max(2, Math.min(120, Number(document.getElementById('cantidad-cuotas-compra').value || 2)));
    const first = document.getElementById('primer-vencimiento-cuota-compra').value;
    const body = document.getElementById('cuotas-compra-body');
    body.innerHTML = '';
    const total = parseFloat(document.getElementById('total-final').textContent || '0');
    const base = Math.floor((total / count) * 100) / 100;
    for (let index = 0; index < count; index++) {
        const date = new Date(`${first}T12:00:00`);
        date.setMonth(date.getMonth() + index);
        const amount = index === count - 1 ? total - (base * (count - 1)) : base;
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${index + 1}<input type="hidden" name="cuotas[${index}][numero]" value="${index + 1}"></td><td><input class="cuota-compra-fecha" type="date" name="cuotas[${index}][fecha_vencimiento]" value="${date.toISOString().slice(0, 10)}" required></td><td><input class="cuota-compra-importe" type="number" step="0.01" min="0.01" name="cuotas[${index}][importe]" value="${money(amount)}" required></td><td><input name="cuotas[${index}][observacion]" maxlength="255"></td>`;
        body.appendChild(tr);
    }
    updateInstallmentSummary();
}

function updateInstallmentSummary() {
    const summary = document.getElementById('resumen-cuotas-compra');
    if (!summary || document.getElementById('calendario-cuotas-compra').hidden) return;
    const total = parseFloat(document.getElementById('total-final').textContent || '0');
    const installments = Array.from(document.querySelectorAll('.cuota-compra-importe')).reduce((sum, input) => sum + (parseFloat(input.value) || 0), 0);
    summary.textContent = `Total compra: Bs ${money(total)} · Total cuotas: Bs ${money(installments)} · Diferencia: Bs ${money(total - installments)}`;
}

function togglePurchaseInstallments() {
    const enabled = document.getElementById('plan-pago-compra-tipo').value === 'cuotas';
    document.querySelectorAll('.cuotas-compra-control').forEach(element => element.hidden = !enabled);
    document.getElementById('calendario-cuotas-compra').hidden = !enabled;
    document.querySelectorAll('#calendario-cuotas-compra input').forEach(input => input.disabled = !enabled);
    if (enabled && document.querySelectorAll('.cuota-compra-importe').length === 0) generatePurchaseInstallments();
}

document.getElementById('producto-selector').addEventListener('change', refreshSelectedProduct);
document.getElementById('buscar-producto').addEventListener('input', filterProducts);
document.getElementById('buscar-proveedor').addEventListener('input', filterProviders);
document.getElementById('costo-producto').addEventListener('input', updateSuggestedPrice);
document.getElementById('porcentaje-utilidad').addEventListener('input', updateSuggestedPrice);
document.getElementById('plan-pago-compra-tipo').addEventListener('change', togglePurchaseInstallments);
document.getElementById('generar-cuotas-compra').addEventListener('click', generatePurchaseInstallments);
document.getElementById('cuotas-compra-body').addEventListener('input', updateInstallmentSummary);
document.getElementById('proveedor-id').addEventListener('change', function () {
    document.getElementById('nit-proveedor').value = this.selectedOptions[0]?.dataset.documento || '';
});
document.querySelector('form[action="{{ route('compras.store') }}"]').addEventListener('submit', function (event) {
    if (document.querySelectorAll('#items tbody tr').length === 0) {
        event.preventDefault();
        document.getElementById('compra-items-alert').classList.add('is-open');
        return;
    }

    document.querySelectorAll('.item-vencimiento').forEach(function (input) {
        input.value = normalizeDateValue(input.value);
    });
});

refreshSelectedProduct();
togglePurchaseInstallments();
</script>
@endsection

