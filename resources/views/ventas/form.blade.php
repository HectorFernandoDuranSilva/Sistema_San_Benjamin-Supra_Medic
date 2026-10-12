@extends('layouts.app')

@section('content')
@php($esNota = old('tipo_documento', $tipoDocumento ?? 'factura') === 'nota_entrega')
@php($facturaStockPendiente = $facturaStockPendiente ?? false)
<div class="top">
    <h1 id="titulo-documento">{{ $esNota ? 'Nueva nota de entrega' : 'Registro de ventas' }}</h1>
    <div class="actions">
        @if(auth()->user()->hasPermission('reportes'))
            <a class="btn secondary" href="{{ route('reportes.documentos') }}">Notas/Facturas</a>
        @endif
        <a class="btn secondary" href="{{ route('clientes.index') }}">Crear cliente</a>
    </div>
</div>
<form id="documento-form" class="card" method="post" action="{{ route(($esModuloNotas ?? false) ? 'notas.store' : 'ventas.store') }}">
@csrf
<div class="row">
    <div class="field">
        <label>Orden</label>
        <select name="nombre_comercial">
            <option value="San Benjamin" @selected(old('nombre_comercial', $commercial['name'] ?? '') === 'San Benjamin')>San Benjamin</option>
            <option value="Supra Medic" @selected(old('nombre_comercial', $commercial['name'] ?? '') === 'Supra Medic')>Supra Medic</option>
        </select>
    </div>
    <div class="field">
        <label>Tipo documento</label>
        <select name="tipo_documento" id="tipo-documento" @disabled(($esModuloNotas ?? false) || $facturaStockPendiente)>
            <option value="factura" @selected(!$esNota)>Factura</option>
            <option value="nota_entrega" @selected($esNota)>Nota de entrega</option>
        </select>
        @if($esModuloNotas ?? false)<input type="hidden" name="tipo_documento" value="nota_entrega">@endif
        @if($facturaStockPendiente)<input type="hidden" name="tipo_documento" value="factura"><input type="hidden" name="factura_stock_pendiente" value="1">@endif
    </div>
    <div class="field"><label>Fecha</label><input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" required></div>
    <div class="field"><label id="etiqueta-documento">{{ $esNota ? 'Referencia de nota' : 'Factura' }}</label><input name="factura" value="{{ old('factura') }}"></div>
    <div class="field"><label>No.</label><input name="numero_venta" value="{{ old('numero_venta', $siguienteNumero) }}"></div>
</div>

<section id="plan-pago" style="margin:16px 0;padding:14px 0;border-top:1px solid var(--line);border-bottom:1px solid var(--line);">
    <div class="row">
        <div class="field">
            <label>Forma de pago</label>
            <select id="plan-pago-tipo" name="plan_pago">
                <option value="contado" @selected(old('plan_pago', 'contado') === 'contado')>Pago único</option>
                <option value="cuotas" @selected(old('plan_pago') === 'cuotas')>Pago en cuotas</option>
            </select>
        </div>
        <div class="field cuotas-control"><label>Número de cuotas</label><input id="cantidad-cuotas" type="number" min="2" max="120" value="{{ old('cuotas') ? count(old('cuotas')) : 2 }}"></div>
        <div class="field cuotas-control"><label>Primer vencimiento</label><input id="primer-vencimiento-cuota" type="date" value="{{ old('cuotas.0.fecha_vencimiento', now()->addMonth()->toDateString()) }}"></div>
        <div class="field cuotas-control" style="justify-content:end;"><button type="button" id="generar-cuotas" class="btn secondary">Generar cuotas</button></div>
    </div>
    <div id="calendario-cuotas" hidden>
        <p style="margin:8px 0;">Revise las fechas y los importes. La suma de las cuotas debe coincidir con el total de la venta.</p>
        <table><thead><tr><th>Cuota</th><th>Fecha de vencimiento</th><th>Importe Bs</th><th>Detalle</th><th></th></tr></thead><tbody id="cuotas-body"></tbody></table>
        <p id="resumen-cuotas" style="margin:10px 0 0;font-weight:700;"></p>
    </div>
</section>

<div class="row">
    <div class="field"><label>NIT</label><input id="nit-cliente" name="nit_cliente" value="{{ old('nit_cliente') }}"></div>
    <div class="field"><label>Buscar cliente</label><input id="buscar-cliente" placeholder="Nombre o NIT"></div>
    <div class="field">
        <label>Cliente</label>
        <select id="cliente-id" name="cliente_id">
            <option value="">Sin cliente</option>
            @foreach($clientes as $c)
                <option value="{{ $c->id }}" data-documento="{{ $c->documento }}" data-busqueda="{{ $c->nombre_mostrable }} {{ $c->nombre }} {{ $c->razon_social }} {{ $c->documento }}" @selected(old('cliente_id')==$c->id)>{{ $c->nombre_mostrable }}</option>
            @endforeach
        </select>
    </div>
    <div class="field"><label>Descuento %</label><input id="descuento-porcentaje" type="number" step="0.01" min="0" max="100" name="descuento_porcentaje" value="{{ old('descuento_porcentaje', 0) }}"></div>
    <div class="field">
        <label>Lista de precios</label>
        <select id="lista-precios" name="lista_precios">
            <option value="Precio de Venta" @selected(old('lista_precios') === 'Precio de Venta')>Precio de Venta</option>
            <option value="Precio Sugerido" @selected(old('lista_precios') === 'Precio Sugerido')>Precio sugerido por dólar/reposición</option>
            <option value="Costo Referencial" @selected(old('lista_precios') === 'Costo Referencial')>Costo Referencial</option>
        </select>
    </div>
</div>

<div class="row">
    <div class="field"><label>Código</label><input id="codigo-producto" readonly></div>
    <div class="field"><label>Buscar producto</label><input id="buscar-producto" placeholder="Código o nombre"></div>
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
    <div class="field"><label>Existencia</label><input id="existencia-producto" readonly></div>
    <div class="field">
        <label>Lote</label>
        <select id="lote-producto"></select>
    </div>
    <div class="field"><label>Vencimiento</label><input id="vencimiento-producto" type="date"></div>
    <div class="field"><label>Comentario</label><input name="observacion" value="{{ old('observacion') }}"></div>
</div>

<div class="row">
    <div class="field"><label>Cantidad</label><input id="cantidad-producto" type="number" step="0.01" min="0.01" value="1"></div>
    <div class="field"><label>Precio</label><input id="precio-producto" type="number" step="0.01" min="0" value="0"></div>
    <div class="field"><label>Costo envío</label><input id="costo-envio-producto" type="number" step="0.01" min="0" value="0"></div>
    <div class="field"><label>Otros costos</label><input id="otros-costos-producto" type="number" step="0.01" min="0" value="0"></div>
</div>

<section id="entrega-sin-stock" class="card" style="margin:0 0 16px;" @if(!$facturaStockPendiente)hidden @endif>
    <strong>{{ $facturaStockPendiente ? 'Factura con stock pendiente' : 'Entrega sin stock' }}</strong>
    @if($facturaStockPendiente)
        <p class="muted" style="margin:6px 0 10px;">Se registrará la factura aunque falte existencia. Cuando se reponga el producto, el inventario podrá regularizarse sin cambiar el documento.</p>
    @endif
    <p id="entrega-sin-stock-resumen" class="muted" style="margin:6px 0 10px;"></p>
    <label style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
        <input id="permitir-salida-sin-stock" type="checkbox" name="permitir_salida_sin_stock" value="1" @checked($facturaStockPendiente || old('permitir_salida_sin_stock')) @required($facturaStockPendiente)>
        {{ $facturaStockPendiente ? 'Confirmo registrar esta factura con stock pendiente' : 'Registrar esta nota aunque el inventario quede negativo' }}
    </label>
    <div id="motivo-salida-sin-stock-field" class="field" hidden>
        <label for="motivo-salida-sin-stock">{{ $facturaStockPendiente ? 'Motivo del stock pendiente' : 'Motivo de la entrega sin stock' }}</label>
        <textarea id="motivo-salida-sin-stock" name="motivo_salida_sin_stock" rows="2" maxlength="1000">{{ old('motivo_salida_sin_stock') }}</textarea>
    </div>
</section>

<div class="actions" style="margin-bottom:12px;">
    <button type="button" class="btn secondary" onclick="addSelectedItem()">Agregar producto</button>
    <button class="btn" id="registrar-documento">{{ $esNota ? 'Registrar nota de entrega' : 'Registrar venta' }}</button>
</div>

<div class="card" style="padding:0;">
    <table id="items">
        <thead>
            <tr>
                <th>Código</th><th>Producto</th><th>Marca</th><th>Procedencia</th><th>Medida</th><th>Lote</th><th>Vencimiento</th><th>Cantidad</th><th>Precio</th><th>Desc</th><th>Subtotal</th><th>Impuesto</th><th>Total</th><th>Costo envío</th><th>Otros costos</th><th></th>
            </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
            <tr>
                <th colspan="9" style="text-align:right;">Totales</th>
                <th id="total-descuento">0.00</th>
                <th id="total-subtotal">0.00</th>
                <th id="total-impuesto">0.00</th>
                <th id="total-final">0.00</th>
                <th id="total-envio">0.00</th>
                <th id="total-otros">0.00</th>
                <th></th>
            </tr>
        </tfoot>
    </table>
</div>
</form>

<div id="venta-items-alert" class="glass-modal-backdrop" role="dialog" aria-modal="true">
    <div class="glass-modal is-error">
        <h2>Revise la venta</h2>
        <p>Agregue al menos un producto para registrar la venta.</p>
        <div class="glass-modal-actions">
            <button type="button" class="btn" data-glass-close>Entendido</button>
        </div>
    </div>
</div>

<script src="{{ asset('js/searchable-select.js') }}"></script>
<script>
document.getElementById('tipo-documento').addEventListener('change', function () {
    const esNota = this.value === 'nota_entrega';
    document.getElementById('titulo-documento').textContent = esNota ? 'Nueva nota de entrega' : 'Registro de ventas';
    document.getElementById('etiqueta-documento').textContent = esNota ? 'Referencia de nota' : 'Factura';
    document.getElementById('registrar-documento').textContent = esNota ? 'Registrar nota de entrega' : 'Registrar venta';
    updateDeliveryWithoutStock();
});
const products = @json($productosVenta);
const savedInstallments = @json(old('cuotas', []));

let idx = 0;

function money(value) {
    return (Number(value) || 0).toFixed(2);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function selectedProduct() {
    const id = Number(document.getElementById('producto-selector').value);
    return products.find(product => Number(product.id) === id);
}

function selectedPrice(product) {
    const list = document.getElementById('lista-precios').value;
    if (list === 'Costo Referencial') return product.cost;
    if (list === 'Precio Sugerido') return product.suggested || product.price;
    return product.price;
}

function selectedLot(product) {
    const select = document.getElementById('lote-producto');
    const id = Number(select.value);
    return (product.lotes || []).find(lote => Number(lote.id) === id) || null;
}

function refreshLotOptions(product) {
    const select = document.getElementById('lote-producto');
    select.innerHTML = '';

    if (! product || ! product.lotes || product.lotes.length === 0) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Sin lote disponible';
        select.appendChild(option);
        document.getElementById('vencimiento-producto').value = '';
        return;
    }

    product.lotes.forEach((lote, index) => {
        const option = document.createElement('option');
        option.value = lote.id;
        option.dataset.vencimiento = lote.fecha_vencimiento || '';
        option.textContent = `${lote.codigo} | vence ${lote.fecha_vencimiento_label} | disp. ${money(lote.cantidad_disponible)}`;
        select.appendChild(option);

        if (index === 0) {
            select.value = lote.id;
        }
    });

    refreshSelectedLotDate();
}

function refreshSelectedLotDate() {
    const option = document.getElementById('lote-producto').selectedOptions[0];
    document.getElementById('vencimiento-producto').value = option?.dataset.vencimiento || '';
}

function refreshSelectedProduct() {
    const product = selectedProduct();
    if (! product) {
        ['codigo-producto', 'existencia-producto', 'precio-producto'].forEach(id => document.getElementById(id).value = '');
        refreshLotOptions(null);
        return;
    }

    document.getElementById('codigo-producto').value = product.codigo || '';
    document.getElementById('marca-producto').value = product.marca || '-';
    document.getElementById('procedencia-producto').value = product.procedencia || '-';
    document.getElementById('existencia-producto').value = money(product.stock);
    document.getElementById('precio-producto').value = money(selectedPrice(product));
    refreshLotOptions(product);
}

function addSelectedItem() {
    const product = selectedProduct();
    if (! product) {
        return;
    }

    const tbody = document.querySelector('#items tbody');
    const tr = document.createElement('tr');
    const cantidad = parseFloat(document.getElementById('cantidad-producto').value || '1');
    const precio = parseFloat(document.getElementById('precio-producto').value || selectedPrice(product) || '0');
    const envio = parseFloat(document.getElementById('costo-envio-producto').value || '0');
    const otros = parseFloat(document.getElementById('otros-costos-producto').value || '0');
    const lote = selectedLot(product);
    const vencimiento = document.getElementById('vencimiento-producto').value || '';
    const lotes = product.lotes || [];
    const loteOptions = lotes.length
        ? lotes.map(item => `<option value="${item.id}" data-vencimiento="${escapeHtml(item.fecha_vencimiento || '')}" ${lote && Number(lote.id) === Number(item.id) ? 'selected' : ''}>${escapeHtml(item.codigo)} | ${escapeHtml(item.fecha_vencimiento_label)} | ${money(item.cantidad_disponible)}</option>`).join('')
        : '<option value="">Automático</option>';
    const vencimientoInput = `<input type="date" name="items[${idx}][fecha_vencimiento]" value="${vencimiento}">`;

    tr.innerHTML = `
        <td>${product.codigo}<input type="hidden" name="items[${idx}][producto_id]" value="${product.id}"></td>
        <td>${product.nombre}</td>
        <td>${escapeHtml(product.marca || '-')}</td>
        <td>${escapeHtml(product.procedencia || '-')}</td>
        <td>${product.medida || ''}</td>
        <td><select class="item-lote" name="items[${idx}][inventario_lote_id]">${loteOptions}</select></td>
        <td>${vencimientoInput}</td>
        <td><input class="item-cantidad" type="number" step="0.01" min="0.01" name="items[${idx}][cantidad]" value="${money(cantidad)}"></td>
        <td><input class="item-precio" type="number" step="0.01" min="0" name="items[${idx}][precio_unitario]" value="${money(precio)}"></td>
        <td><input class="item-descuento" type="number" step="0.01" min="0" name="items[${idx}][descuento]" value="0.00"></td>
        <td class="item-subtotal">0.00</td>
        <td><input class="item-impuesto" type="number" step="0.01" min="0" name="items[${idx}][impuesto]" value="0.00"></td>
        <td class="item-total">0.00</td>
        <td><input class="item-envio" type="number" step="0.01" min="0" name="items[${idx}][costo_envio]" value="${money(envio)}"></td>
        <td><input class="item-otros" type="number" step="0.01" min="0" name="items[${idx}][otros_costos]" value="${money(otros)}"></td>
        <td><button type="button" class="btn secondary" onclick="this.closest('tr').remove(); updateTotals();">Quitar</button></td>
    `;

    tbody.appendChild(tr);
    tr.querySelectorAll('input').forEach(input => input.addEventListener('input', () => updateRow(tr)));
    tr.querySelector('.item-lote').addEventListener('change', function () {
        const option = this.selectedOptions[0];
        const dateInput = this.closest('tr').querySelector('input[name$="[fecha_vencimiento]"]');
        dateInput.value = option?.dataset.vencimiento || '';
    });
    updateRow(tr);
    idx++;
}

function updateRow(tr) {
    const cantidad = parseFloat(tr.querySelector('.item-cantidad').value || '0');
    const precio = parseFloat(tr.querySelector('.item-precio').value || '0');
    const porcentaje = parseFloat(document.getElementById('descuento-porcentaje').value || '0');
    const subtotal = cantidad * precio;
    const descuentoInput = tr.querySelector('.item-descuento');

    if (document.activeElement !== descuentoInput) {
        descuentoInput.value = money(subtotal * (porcentaje / 100));
    }

    const descuento = parseFloat(descuentoInput.value || '0');
    const impuesto = parseFloat(tr.querySelector('.item-impuesto').value || '0');
    const envio = parseFloat(tr.querySelector('.item-envio').value || '0');
    const otros = parseFloat(tr.querySelector('.item-otros').value || '0');
    const total = Math.max(0, subtotal - descuento) + impuesto + envio + otros;

    tr.querySelector('.item-subtotal').textContent = money(subtotal);
    tr.querySelector('.item-total').textContent = money(total);
    updateTotals();
}

function updateTotals() {
    let subtotal = 0;
    let descuento = 0;
    let impuesto = 0;
    let total = 0;
    let envio = 0;
    let otros = 0;

    document.querySelectorAll('#items tbody tr').forEach(tr => {
        subtotal += parseFloat(tr.querySelector('.item-subtotal').textContent || '0');
        descuento += parseFloat(tr.querySelector('.item-descuento').value || '0');
        impuesto += parseFloat(tr.querySelector('.item-impuesto').value || '0');
        total += parseFloat(tr.querySelector('.item-total').textContent || '0');
        envio += parseFloat(tr.querySelector('.item-envio').value || '0');
        otros += parseFloat(tr.querySelector('.item-otros').value || '0');
    });

    document.getElementById('total-descuento').textContent = money(descuento);
    document.getElementById('total-subtotal').textContent = money(subtotal);
    document.getElementById('total-impuesto').textContent = money(impuesto);
    document.getElementById('total-final').textContent = money(total);
    document.getElementById('total-envio').textContent = money(envio);
    document.getElementById('total-otros').textContent = money(otros);
    updateDeliveryWithoutStock();
    updateInstallmentSummary();
}

function updateDeliveryWithoutStock() {
    const documentType = document.getElementById('tipo-documento').value;
    const section = document.getElementById('entrega-sin-stock');
    const checkbox = document.getElementById('permitir-salida-sin-stock');
    const reasonField = document.getElementById('motivo-salida-sin-stock-field');
    const reason = document.getElementById('motivo-salida-sin-stock');
    const quantities = new Map();

    document.querySelectorAll('#items tbody tr').forEach(row => {
        const productId = Number(row.querySelector('input[name$="[producto_id]"]').value);
        const quantity = Number(row.querySelector('.item-cantidad').value || 0);
        quantities.set(productId, (quantities.get(productId) || 0) + quantity);
    });

    const shortages = Array.from(quantities.entries()).map(([productId, quantity]) => {
        const product = products.find(item => Number(item.id) === productId);
        const available = Number(product?.stock_disponible ?? product?.stock ?? 0);
        return { product, shortage: quantity - available };
    }).filter(item => item.shortage > 0.0001);

    const facturaStockPendiente = @json($facturaStockPendiente);
    const needsException = facturaStockPendiente || (documentType === 'nota_entrega' && shortages.length > 0);
    section.hidden = !needsException;
    checkbox.disabled = !needsException;
    if (!needsException) checkbox.checked = false;
    if (facturaStockPendiente) checkbox.checked = true;
    reason.disabled = !needsException || !checkbox.checked;
    reason.required = needsException && checkbox.checked;
    reasonField.hidden = !checkbox.checked;
    document.getElementById('entrega-sin-stock-resumen').textContent = shortages
        .map(item => `${item.product?.codigo || 'Producto'}: faltan ${money(item.shortage)}`)
        .join(' · ');
}

function installmentDate(start, monthOffset) {
    const date = new Date(`${start}T12:00:00`);
    date.setMonth(date.getMonth() + monthOffset);
    return date.toISOString().slice(0, 10);
}

function installmentRow(index, installment = {}) {
    const body = document.getElementById('cuotas-body');
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${index + 1}</td>
        <td><input type="date" name="cuotas[${index}][fecha_vencimiento]" value="${escapeHtml(installment.fecha_vencimiento || '')}" required></td>
        <td><input class="cuota-importe" type="number" min="0.01" step="0.01" name="cuotas[${index}][importe]" value="${escapeHtml(installment.importe || '')}" required></td>
        <td><input name="cuotas[${index}][observacion]" maxlength="255" value="${escapeHtml(installment.observacion || '')}"></td>
        <td><button type="button" class="btn secondary" title="Quitar cuota">Quitar</button></td>`;
    tr.querySelector('.cuota-importe').addEventListener('input', updateInstallmentSummary);
    tr.querySelector('button').addEventListener('click', () => { tr.remove(); renumberInstallments(); updateInstallmentSummary(); });
    body.appendChild(tr);
}

function renumberInstallments() {
    document.querySelectorAll('#cuotas-body tr').forEach((tr, index) => {
        tr.cells[0].textContent = index + 1;
        tr.querySelectorAll('input').forEach(input => { input.name = input.name.replace(/cuotas\\[\\d+\\]/, `cuotas[${index}]`); });
    });
}

function generateInstallments() {
    const count = Math.max(2, Math.min(120, Number(document.getElementById('cantidad-cuotas').value || 2)));
    const firstDate = document.getElementById('primer-vencimiento-cuota').value;
    const totalCents = Math.round(Number(document.getElementById('total-final').textContent || 0) * 100);
    const base = Math.floor(totalCents / count);
    const remainder = totalCents - (base * count);
    const body = document.getElementById('cuotas-body');
    body.innerHTML = '';
    for (let index = 0; index < count; index++) {
        installmentRow(index, {
            fecha_vencimiento: firstDate ? installmentDate(firstDate, index) : '',
            importe: money((base + (index === count - 1 ? remainder : 0)) / 100),
        });
    }
    updateInstallmentSummary();
}

function updateInstallmentSummary() {
    const calendar = document.getElementById('calendario-cuotas');
    if (calendar.hidden) return;
    const installmentsTotal = Array.from(document.querySelectorAll('.cuota-importe')).reduce((sum, input) => sum + Number(input.value || 0), 0);
    const saleTotal = Number(document.getElementById('total-final').textContent || 0);
    const difference = Math.round((saleTotal - installmentsTotal) * 100) / 100;
    document.getElementById('resumen-cuotas').textContent = `Total venta: Bs ${money(saleTotal)} · Total cuotas: Bs ${money(installmentsTotal)} · Diferencia: Bs ${money(difference)}`;
}

function toggleInstallments() {
    const enabled = document.getElementById('plan-pago-tipo').value === 'cuotas';
    document.querySelectorAll('.cuotas-control').forEach(element => element.hidden = !enabled);
    document.getElementById('calendario-cuotas').hidden = !enabled;
    document.querySelectorAll('#calendario-cuotas input').forEach(input => input.disabled = !enabled);
    if (enabled && document.querySelectorAll('#cuotas-body tr').length === 0) {
        if (savedInstallments.length) savedInstallments.forEach((installment, index) => installmentRow(index, installment));
        else generateInstallments();
    }
    document.querySelectorAll('#calendario-cuotas input').forEach(input => input.disabled = !enabled);
    updateInstallmentSummary();
}

document.getElementById('producto-selector').addEventListener('change', refreshSelectedProduct);
document.getElementById('lista-precios').addEventListener('change', refreshSelectedProduct);
document.getElementById('lote-producto').addEventListener('change', refreshSelectedLotDate);
document.getElementById('buscar-producto').addEventListener('input', function () {
    filterSearchableSelect(document.getElementById('producto-selector'), this.value, refreshSelectedProduct);
});
document.getElementById('descuento-porcentaje').addEventListener('input', function () {
    document.querySelectorAll('#items tbody tr').forEach(updateRow);
});
document.getElementById('plan-pago-tipo').addEventListener('change', toggleInstallments);
document.getElementById('generar-cuotas').addEventListener('click', generateInstallments);
document.getElementById('permitir-salida-sin-stock').addEventListener('change', updateDeliveryWithoutStock);
document.getElementById('cliente-id').addEventListener('change', function () {
    document.getElementById('nit-cliente').value = this.selectedOptions[0]?.dataset.documento || '';
});
document.getElementById('buscar-cliente').addEventListener('input', function () {
    const select = document.getElementById('cliente-id');
    filterSearchableSelect(select, this.value, () => {
        document.getElementById('nit-cliente').value = select.selectedOptions[0]?.dataset.documento || '';
    }, true);
});
document.getElementById('documento-form').addEventListener('submit', function (event) {
    if (document.querySelectorAll('#items tbody tr').length === 0) {
        event.preventDefault();
        document.getElementById('venta-items-alert').classList.add('is-open');
    }
});

refreshSelectedProduct();
toggleInstallments();
updateDeliveryWithoutStock();
</script>
@endsection

