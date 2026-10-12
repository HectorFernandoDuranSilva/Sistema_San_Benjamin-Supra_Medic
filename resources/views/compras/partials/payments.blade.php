<section style="margin-top:24px;">
@php($payments = $compra->pagos->sortBy(['fecha', 'id']))
@php($paid = $payments->where('estado', 'registrado')->sum('importe'))
@php($installments = $compra->cuotas ?? collect())
<h2>Pagos a proveedor</h2>
<p><strong>Estado: {{ $paid >= (float) $compra->total ? 'Pagada' : ($paid > 0 ? 'Pago parcial' : 'Por pagar') }}</strong> · Total: Bs {{ number_format($compra->total, 2) }} · Pagado: Bs {{ number_format($paid, 2) }} · Saldo: Bs {{ number_format(max(0, $compra->total - $paid), 2) }}</p>
@if($installments->isNotEmpty())
<table><tr><th>Cuota</th><th>Vencimiento</th><th>Importe</th><th>Pagado</th><th>Saldo</th><th>Estado</th></tr>
@foreach($installments as $installment)
    @php($installmentPaid = (float) $installment->pagos->where('estado', 'registrado')->sum('importe'))
    @php($installmentBalance = max(0, (float) $installment->importe - $installmentPaid))
    <tr><td>{{ $installment->numero }}</td><td>{{ $installment->fecha_vencimiento->format('d/m/Y') }}</td><td>Bs {{ number_format($installment->importe, 2) }}</td><td>Bs {{ number_format($installmentPaid, 2) }}</td><td>Bs {{ number_format($installmentBalance, 2) }}</td><td>{{ $installmentBalance <= 0 ? 'Pagada' : ($installment->fecha_vencimiento->lt(today()) ? 'Vencida' : 'Pendiente') }}</td></tr>
@endforeach
</table>
@endif
@if($compra->estado === 'registrada' && $compra->total > $paid)
<form method="post" action="{{ route('compras.pagos.store', $compra) }}" data-glass-confirm="¿Confirmar el pago al proveedor?">
@csrf<input type="hidden" name="token" value="{{ old('token', (string) \Illuminate\Support\Str::uuid()) }}">
<div class="row">
    <div class="field"><label>Fecha de pago</label><input type="date" name="fecha" value="{{ old('fecha', today()->toDateString()) }}" required></div>
    <div class="field"><label>Importe Bs</label><input type="number" name="importe" min="0.01" max="{{ number_format($compra->total - $paid, 2, '.', '') }}" step="0.01" required></div>
    @if($installments->isNotEmpty())<div class="field"><label>Cuota</label><select name="cuota_id" required><option value="">Seleccione una cuota</option>@foreach($installments as $installment)@php($installmentBalance = max(0, (float) $installment->importe - (float) $installment->pagos->where('estado', 'registrado')->sum('importe'))) @if($installmentBalance > 0)<option value="{{ $installment->id }}">Cuota {{ $installment->numero }} · vence {{ $installment->fecha_vencimiento->format('d/m/Y') }} · saldo Bs {{ number_format($installmentBalance, 2) }}</option>@endif @endforeach</select></div>@endif
    <div class="field"><label>Medio de pago</label><select name="medio"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="deposito">Depósito</option></select></div>
    <div class="field"><label>Referencia</label><input name="referencia" maxlength="255"></div>
</div>
<div class="actions"><button type="button" class="btn secondary" data-finiquitar-compra data-saldo="{{ number_format($compra->total - $paid, 2, '.', '') }}">Finiquitar saldo</button><button class="btn">Registrar pago</button></div>
</form>
@endif
<table>
    <tr><th>Fecha</th><th>Importe</th><th>Medio</th><th>Referencia</th><th>Estado</th><th>Corrección</th></tr>
    @forelse($payments as $payment)
        <tr>
            <td>{{ $payment->fecha->format('d/m/Y') }}</td><td>Bs {{ number_format($payment->importe, 2) }}</td><td>{{ ucfirst($payment->medio) }}</td><td>{{ $payment->referencia }}</td><td>{{ ucfirst($payment->estado) }}</td>
            <td>@if($payment->estado === 'registrado' && auth()->user()->isAdmin())<details><summary>Anular pago</summary><form method="post" action="{{ route('pagos-compras.anular', $payment) }}" data-glass-confirm="¿Anular este pago? El registro se conservará en el histórico.">@csrf<label>Motivo</label><input name="motivo" maxlength="255" required><label>Código de Google Authenticator</label><input name="totp_code" inputmode="numeric" maxlength="32" required><button class="btn danger">Confirmar anulación</button></form></details>@else{{ $payment->motivo_anulacion }}@endif</td>
        </tr>
    @empty
        <tr><td colspan="6">Sin pagos registrados.</td></tr>
    @endforelse
</table>
<script>document.querySelectorAll('[data-finiquitar-compra]').forEach(function(button){button.addEventListener('click',function(){const input=button.closest('form')?.querySelector('input[name="importe"]');if(input) input.value=button.dataset.saldo;});});</script>
</section>
