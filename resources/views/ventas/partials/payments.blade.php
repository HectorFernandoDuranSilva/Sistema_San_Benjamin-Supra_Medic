@if(! $cuotasDisponibles)
    <div class="card" style="margin-top:24px;"><p>Las cuotas y cobros estarán disponibles cuando termine la actualización de la base de datos.</p></div>
@else
<section id="cobros" style="margin-top:24px;">
@php($payments = $venta->cobros()->with('cuota')->orderBy('fecha')->orderBy('id')->get())
@php($paid = $payments->where('estado', 'registrado')->sum('importe'))
@php($installments = $venta->cuotas()->with('cobros')->orderBy('numero')->get())
<h2>Cobros</h2>
<p><strong>Estado: {{ $paid >= (float) $venta->total ? 'Pagada' : ($paid > 0 ? 'Pago parcial' : 'Por cobrar') }}</strong> · Total: Bs {{ number_format($venta->total, 2) }} · Cobrado: Bs {{ number_format($paid, 2) }} · Saldo: Bs {{ number_format(max(0, $venta->total - $paid), 2) }}</p>

@if($installments->isNotEmpty())
    <table>
        <tr><th>Cuota</th><th>Vencimiento</th><th>Importe</th><th>Cobrado</th><th>Saldo</th><th>Estado</th></tr>
        @foreach($installments as $installment)
            @php($installmentPaid = (float) $installment->cobros->where('estado', 'registrado')->sum('importe'))
            @php($installmentBalance = max(0, (float) $installment->importe - $installmentPaid))
            <tr>
                <td>{{ $installment->numero }}</td>
                <td>{{ $installment->fecha_vencimiento->format('d/m/Y') }}</td>
                <td>Bs {{ number_format($installment->importe, 2) }}</td>
                <td>Bs {{ number_format($installmentPaid, 2) }}</td>
                <td>Bs {{ number_format($installmentBalance, 2) }}</td>
                <td>{{ $installmentBalance <= 0 ? 'Pagada' : ($installment->fecha_vencimiento->lt(today()) ? 'Vencida' : 'Pendiente') }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if($venta->estado !== 'anulada' && $venta->total > $paid)
    <form method="post" action="{{ route('ventas.cobros.store', $venta) }}" data-glass-confirm="¿Confirmar el cobro recibido?">
        @csrf
        <input type="hidden" name="token" value="{{ old('token', (string) \Illuminate\Support\Str::uuid()) }}">
        <div class="row">
            <div class="field"><label>Fecha de cobro</label><input type="date" name="fecha" value="{{ old('fecha', today()->toDateString()) }}" required></div>
            <div class="field"><label>Importe Bs</label><input type="number" name="importe" min="0.01" max="{{ number_format($venta->total - $paid, 2, '.', '') }}" step="0.01" required></div>
            @if($installments->isNotEmpty())
                <div class="field">
                    <label>Cuota</label>
                    <select name="cuota_id" required>
                        <option value="">Seleccione una cuota</option>
                        @foreach($installments as $installment)
                            @php($installmentBalance = max(0, (float) $installment->importe - (float) $installment->cobros->where('estado', 'registrado')->sum('importe')))
                            @if($installmentBalance > 0)
                                <option value="{{ $installment->id }}" @selected(old('cuota_id') == $installment->id)>Cuota {{ $installment->numero }} · vence {{ $installment->fecha_vencimiento->format('d/m/Y') }} · saldo Bs {{ number_format($installmentBalance, 2) }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="field"><label>Medio de pago</label><select name="medio"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="deposito">Depósito</option></select></div>
            <div class="field"><label>Referencia</label><input name="referencia" maxlength="255"></div>
        </div>
        <div class="actions"><button type="button" class="btn secondary" data-finiquitar data-saldo="{{ number_format($venta->total - $paid, 2, '.', '') }}">Finiquitar saldo</button><button class="btn">Registrar cobro</button></div>
    </form>
@endif

<table>
    <tr><th>Fecha</th><th>Cuota</th><th>Importe</th><th>Medio</th><th>Referencia</th><th>Estado</th><th>Corrección</th></tr>
    @foreach($payments as $payment)
        <tr>
            <td>{{ $payment->fecha->format('d/m/Y') }}</td>
            <td>{{ $payment->cuota?->numero ?? '-' }}</td>
            <td>Bs {{ number_format($payment->importe, 2) }}</td>
            <td>{{ ucfirst($payment->medio) }}</td>
            <td>{{ $payment->referencia }}</td>
            <td>{{ ucfirst($payment->estado) }}</td>
            <td>
                @if($payment->estado === 'registrado' && auth()->user()->isAdmin())
                    <details>
                        <summary>Anular cobro</summary>
                        <form method="post" action="{{ route('cobros.anular', $payment) }}" data-glass-confirm="¿Anular este cobro? El registro se conservará en el histórico.">
                            @csrf
                            <label>Motivo</label><input name="motivo" maxlength="255" required>
                            <label>Código de Google Authenticator</label><input name="totp_code" inputmode="numeric" maxlength="32" required>
                            <button class="btn danger">Confirmar anulación</button>
                        </form>
                    </details>
                @else
                    {{ $payment->motivo_anulacion }}
                @endif
            </td>
        </tr>
    @endforeach
</table>
<script>
document.querySelectorAll('[data-finiquitar]').forEach(function (button) {
    button.addEventListener('click', function () {
        const form = button.closest('form');
        const amount = form?.querySelector('input[name="importe"]');
        if (amount) amount.value = button.dataset.saldo;
    });
});
</script>
</section>
@endif
