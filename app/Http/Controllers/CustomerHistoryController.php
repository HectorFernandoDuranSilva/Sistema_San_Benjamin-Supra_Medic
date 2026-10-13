<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\Venta;
use App\Models\VentaCuota;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerHistoryController extends Controller
{
    private function filters(Request $request): array
    {
        return $request->validate([
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'desde' => ['nullable', 'date'], 'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'nombre_comercial' => ['nullable', 'in:San Benjamin,Supra Medic'],
            'anio' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'tipo_documento' => ['nullable', 'in:factura,nota_entrega'],
        ]);
    }

    private function sales(array $f)
    {
        return Venta::with('cliente', 'detalles.lotes.lote', 'cobros')
            ->when($f['cliente_id'] ?? null, fn ($q, $id) => $q->where('cliente_id', $id))
            ->when($f['nombre_comercial'] ?? null, fn ($q, $brand) => $q->where('nombre_comercial', $brand))
            ->when($f['anio'] ?? null, fn ($q, $year) => $q->whereYear('fecha', $year))
            ->when($f['tipo_documento'] ?? null, fn ($q, $type) => $q->where('tipo_documento', $type))
            ->when($f['desde'] ?? null, fn ($q, $date) => $q->whereDate('fecha', '>=', $date))
            ->when($f['hasta'] ?? null, fn ($q, $date) => $q->whereDate('fecha', '<=', $date));
    }

    public function index(Request $request)
    {
        $f = $this->filters($request);
        $query = $this->sales($f);
        $totales = (clone $query)->where('estado', '!=', 'anulada')->selectRaw('nombre_comercial, tipo_documento, SUM(total) as importe')->groupBy('nombre_comercial', 'tipo_documento')->get();
        // Payment dates are independent of invoice dates.
        $cobros = Cobro::with('venta.cliente')->whereHas('venta', function ($q) use ($f) {
            $q->when($f['cliente_id'] ?? null, fn ($q, $id) => $q->where('cliente_id', $id))
                ->when($f['nombre_comercial'] ?? null, fn ($q, $brand) => $q->where('nombre_comercial', $brand));
        })->when($f['desde'] ?? null, fn ($q, $date) => $q->whereDate('fecha', '>=', $date))
          ->when($f['hasta'] ?? null, fn ($q, $date) => $q->whereDate('fecha', '<=', $date));
        return view('clientes.historial', [
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(), 'totales' => $totales,
            'ventas' => $query->orderByDesc('fecha')->orderByDesc('id')->paginate(15, ['*'], 'ventas_page')->withQueryString(),
            'totalCobrado' => (clone $cobros)->where('estado', 'registrado')->sum('importe'),
            'cobros' => $cobros->latest('fecha')->latest('id')->paginate(15, ['*'], 'cobros_page')->withQueryString(),
        ]);
    }

    public function receivables(Request $request)
    {
        $documentos = Venta::with(['cliente', 'cobros', 'cuotas.cobros'])
            ->where('estado', 'registrada')
            ->when($request->input('tipo_documento'), fn ($q, $tipo) => $q->where('tipo_documento', $tipo))
            ->when($request->input('nombre_comercial'), fn ($q, $nombre) => $q->where('nombre_comercial', $nombre))
            ->when(trim((string) $request->input('buscar')) !== '', function ($q) use ($request) {
                $buscar = '%' . trim((string) $request->input('buscar')) . '%';
                $q->where(function ($q) use ($buscar) {
                    $q->where('numero_venta', 'like', $buscar)
                        ->orWhere('factura', 'like', $buscar)
                        ->orWhere('nit_cliente', 'like', $buscar)
                        ->orWhereHas('cliente', fn ($cliente) => $cliente->where('nombre', 'like', $buscar));
                });
            })
            ->latest('fecha')->latest('id')->get()
            ->map(function (Venta $venta) {
                $pagado = (float) $venta->cobros->where('estado', 'registrado')->sum('importe');
                $venta->cobrado_actual = $pagado;
                $venta->saldo_actual = max(0, (float) $venta->total - $pagado);
                $venta->cuotas_pendientes = $venta->cuotas->filter(function ($cuota) {
                    return (float) $cuota->importe > (float) $cuota->cobros->where('estado', 'registrado')->sum('importe');
                })->count();
                $venta->estado_pago = $venta->saldo_actual <= 0 ? 'Pagada' : ($pagado > 0 ? 'Pago parcial' : 'Por cobrar');

                return $venta;
            })
            ->filter(fn (Venta $venta) => $venta->saldo_actual > 0)
            ->values();

        return view('clientes.cobros', [
            'documentos' => $documentos,
            'filtros' => $request->only(['buscar', 'tipo_documento', 'nombre_comercial']),
        ]);
    }

    public function pdf(Request $request)
    {
        $f = $this->filters($request); $query = $this->sales($f);
        if ($query->count() > 500) {
            return back()->withErrors(['reporte' => 'Seleccione un cliente o reduzca el período: el PDF admite hasta 500 operaciones.']);
        }
        return Pdf::loadView('clientes.historial-pdf', ['ventas' => $query->orderBy('fecha')->orderBy('id')->get(), 'filtros' => $f])->setPaper('a4', 'landscape')->stream('historico-cliente.pdf');
    }

    public function storePayment(Request $request, Venta $venta, AuditLogService $audit)
    {
        if (! Schema::hasTable('venta_cuotas')) {
            return back()->withErrors(['cuota_id' => 'La actualización de la base de datos está pendiente. No se pueden registrar cobros por cuotas todavía.']);
        }

        $data = $request->validate([
            'token' => ['required', 'uuid'], 'fecha' => ['required', 'date', 'before_or_equal:today'],
            'importe' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999'],
            'medio' => ['required', 'in:efectivo,transferencia,deposito'], 'referencia' => ['nullable', 'string', 'max:255'],
            'cuota_id' => ['nullable', 'integer', 'exists:venta_cuotas,id'],
        ]);
        return DB::transaction(function () use ($request, $venta, $audit, $data) {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            if ($existing = Cobro::where('token', $data['token'])->first()) {
                abort_unless($existing->venta_id === $venta->id, 409);
                return back()->with('ok', 'Este cobro ya estaba registrado.');
            }
            $saldo = round((float) $venta->total * 100) - round((float) $venta->cobros()->where('estado', 'registrado')->sum('importe') * 100);
            if ($venta->estado === 'anulada' || round((float) $data['importe'] * 100) > $saldo) {
                return back()->withErrors(['importe' => 'El importe supera el saldo pendiente o el documento está anulado.']);
            }
            $cuotas = VentaCuota::where('venta_id', $venta->id)->lockForUpdate()->get();
            if ($cuotas->isNotEmpty()) {
                $cuota = $cuotas->firstWhere('id', (int) ($data['cuota_id'] ?? 0));
                if (! $cuota) {
                    return back()->withErrors(['cuota_id' => 'Seleccione la cuota a la que corresponde este cobro.']);
                }
                $saldoCuota = round((float) $cuota->importe * 100) - round((float) $cuota->cobros()->where('estado', 'registrado')->sum('importe') * 100);
                if (round((float) $data['importe'] * 100) > $saldoCuota) {
                    return back()->withErrors(['importe' => 'El importe supera el saldo pendiente de la cuota seleccionada.']);
                }
            } elseif (! empty($data['cuota_id'])) {
                return back()->withErrors(['cuota_id' => 'Esta venta no tiene un calendario de cuotas.']);
            }
            $cobro = $venta->cobros()->create($data + ['usuario_id' => $request->user()->id]);
            $audit->record($request, 'registro_cobro', 'ventas', $cobro, null, $cobro->toArray(), 'Cobro registrado');
            return back()->with('ok', 'Cobro registrado.');
        });
    }

    public function cancelPayment(Request $request, Cobro $cobro, AuditLogService $audit)
    {
        $data = $request->validate(['totp_code' => ['required', 'string', 'max:32'], 'motivo' => ['required', 'string', 'max:255']]);
        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'ventas', $cobro, 'Intento de anular cobro')) {
            return back()->withErrors(['totp_code' => $error]);
        }
        return DB::transaction(function () use ($request, $cobro, $audit, $data) {
            Venta::whereKey($cobro->venta_id)->lockForUpdate()->firstOrFail();
            $cobro = Cobro::whereKey($cobro->id)->lockForUpdate()->firstOrFail();
            if ($cobro->estado !== 'anulado') {
                $before = $cobro->toArray();
                $cobro->update(['estado' => 'anulado', 'motivo_anulacion' => $data['motivo']]);
                $audit->record($request, 'anulo_cobro', 'ventas', $cobro, $before, $cobro->toArray(), 'Corrección de cobro');
            }
            return back()->with('ok', 'Cobro anulado. El registro permanece en el histórico.');
        });
    }
}
