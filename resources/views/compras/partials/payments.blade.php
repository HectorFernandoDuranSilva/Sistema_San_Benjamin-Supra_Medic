<section style="margin-top:24px;">
@php($payments = $compra->pagos->sortBy(['fecha', 'id']))
@php($paid = $payments->where('estado', 'registrado')->sum('importe'))
<h2>Pagos a proveedor</h2>
<p>Pagado: Bs {{ number_format($paid, 2) }} | Saldo: Bs {{ number_format(max(0, $compra->total - $paid), 2) }}</p>
@if($compra->estado === 'registrada' && $compra->total > $paid)
<form method="post" action="{{ route('compras.pagos.store', $compra) }}" data-glass-confirm="¿Confirmar el pago al proveedor?">
@csrf<input type="hidden" name="token" value="{{ old('token', (string) \Illuminate\Support\Str::uuid()) }}">
<div class="row">
    <div class="field"><label>Fecha de pago</label><input type="date" name="fecha" value="{{ old('fecha', today()->toDateString()) }}" required></div>
    <div class="field"><label>Importe Bs</label><input type="number" name="importe" min="0.01" max="{{ number_format($compra->total - $paid, 2, '.', '') }}" step="0.01" required></div>
    <div class="field"><label>Medio de pago</label><select name="medio"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="deposito">Depósito</option></select></div>
    <div class="field"><label>Referencia</label><input name="referencia" maxlength="255"></div>
</div>
<button class="btn">Registrar pago</button>
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
</section>
