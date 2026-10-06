<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequest;
use App\Models\Compra;
use App\Models\ConfiguracionEmpresa;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\PagoCompra;
use App\Services\AuditLogService;
use App\Services\PurchaseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'buscar' => ['nullable', 'string', 'max:255'],
            'tipo_documento' => ['nullable', 'in:factura,nota_entrega'],
            'nombre_comercial' => ['nullable', 'in:San Benjamin,Supra Medic'],
            'anio' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $compras = Compra::with('proveedor', 'usuario')
            ->withSum(['pagos as pagado' => fn ($query) => $query->where('estado', 'registrado')], 'importe')
            ->withMax(['pagos as fecha_ultimo_pago' => fn ($query) => $query->where('estado', 'registrado')], 'fecha')
            ->when($filters['buscar'] ?? null, function ($query, $buscar) {
                $buscar = '%' . trim($buscar) . '%';
                $query->where(function ($query) use ($buscar) {
                    $query->where('numero_compra', 'like', $buscar)
                        ->orWhere('factura', 'like', $buscar)
                        ->orWhere('nit_proveedor', 'like', $buscar)
                        ->orWhereHas('proveedor', fn ($proveedor) => $proveedor->where('nombre', 'like', $buscar));
                });
            })
            ->when($filters['tipo_documento'] ?? null, fn ($query, $tipo) => $query->where('tipo_documento', $tipo))
            ->when($filters['nombre_comercial'] ?? null, fn ($query, $nombre) => $query->where('nombre_comercial', $nombre))
            ->when($filters['anio'] ?? null, fn ($query, $anio) => $query->whereYear('fecha', $anio))
            ->latest('fecha')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('compras.index', compact('compras', 'filters'));
    }

    public function create()
    {
        return view('compras.form', [
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(),
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(),
            'commercial' => session('commercial_context'),
        ]);
    }

    public function store(PurchaseRequest $request, PurchaseService $service)
    {
        try {
            $compra = $service->registrar($request->validated(), $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['compra' => $e->getMessage()])->withInput();
        }

        return redirect()->route('compras.show', $compra)->with('ok', 'Compra registrada.');
    }

    public function show(Compra $compra)
    {
        return view('compras.show', ['compra' => $compra->load('proveedor', 'detalles.producto', 'detalles.lote', 'usuario', 'pagos')]);
    }

    public function storePayment(Request $request, Compra $compra, AuditLogService $audit)
    {
        $data = $request->validate([
            'token' => ['required', 'uuid'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'importe' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999'],
            'medio' => ['required', 'in:efectivo,transferencia,deposito'],
            'referencia' => ['nullable', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($request, $compra, $audit, $data) {
            $compra = Compra::whereKey($compra->id)->lockForUpdate()->firstOrFail();
            if ($existing = PagoCompra::where('token', $data['token'])->first()) {
                abort_unless($existing->compra_id === $compra->id, 409);

                return back()->with('ok', 'Este pago ya estaba registrado.');
            }

            $saldo = round((float) $compra->total * 100) - round((float) $compra->pagos()->where('estado', 'registrado')->sum('importe') * 100);
            if ($compra->estado !== 'registrada' || round((float) $data['importe'] * 100) > $saldo) {
                return back()->withErrors(['importe' => 'El importe supera el saldo pendiente o el documento no está registrado.']);
            }

            $pago = $compra->pagos()->create($data + ['usuario_id' => $request->user()->id]);
            $audit->record($request, 'registro_pago_proveedor', 'compras', $pago, null, $pago->toArray(), 'Pago a proveedor registrado');

            return back()->with('ok', 'Pago a proveedor registrado.');
        });
    }

    public function cancelPayment(Request $request, PagoCompra $pago, AuditLogService $audit)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $data = $request->validate([
            'totp_code' => ['required', 'string', 'max:32'],
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'compras', $pago, 'Intento de anular pago a proveedor')) {
            return back()->withErrors(['totp_code' => $error]);
        }

        return DB::transaction(function () use ($request, $pago, $audit, $data) {
            Compra::whereKey($pago->compra_id)->lockForUpdate()->firstOrFail();
            $pago = PagoCompra::whereKey($pago->id)->lockForUpdate()->firstOrFail();
            if ($pago->estado !== 'anulado') {
                $before = $pago->toArray();
                $pago->update(['estado' => 'anulado', 'motivo_anulacion' => $data['motivo']]);
                $audit->record($request, 'anulo_pago_proveedor', 'compras', $pago, $before, $pago->toArray(), 'Corrección de pago a proveedor');
            }

            return back()->with('ok', 'Pago a proveedor anulado. El registro permanece en el histórico.');
        });
    }

    public function editHistory(Compra $compra)
    {
        return view('compras.edit-history', [
            'compra' => $compra->load('proveedor'),
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function updateHistory(Request $request, Compra $compra)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'totp_code' => ['required', 'string', 'max:32'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'tipo_documento' => ['nullable', 'in:factura,nota_entrega'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'fecha' => ['required', 'date'],
            'factura' => ['nullable', 'string', 'max:255'],
            'nit_proveedor' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string'],
        ]);

        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'compras', $compra, 'Intento de editar historial de compra')) {
            return back()->withErrors(['totp_code' => $error])->withInput();
        }

        $before = $compra->only(['nombre_comercial', 'tipo_documento', 'proveedor_id', 'fecha', 'factura', 'nit_proveedor', 'observacion']);
        unset($data['totp_code']);
        $compra->update($data);
        app(AuditLogService::class)->record(
            $request,
            'actualizo',
            'compras',
            $compra,
            $before,
            $compra->only(array_keys($before)),
            'Edicion segura de cabecera de compra'
        );

        return redirect()->route('compras.show', $compra)->with('ok', 'Cabecera de compra actualizada sin modificar inventario.');
    }

    public function recibo(Compra $compra)
    {
        return Pdf::loadView('recibos.compra', $this->receiptData($compra) + ['isPdf' => true])
            ->setPaper('letter')
            ->stream("recibo-compra-{$compra->numero_compra}.pdf");
    }

    public function pdf(Compra $compra)
    {
        return Pdf::loadView('recibos.compra', $this->receiptData($compra) + ['isPdf' => true])
            ->setPaper('letter')
            ->download("recibo-compra-{$compra->numero_compra}.pdf");
    }

    private function receiptData(Compra $compra): array
    {
        return [
            'compra' => $compra->load('proveedor', 'detalles.producto', 'usuario'),
            'empresa' => ConfiguracionEmpresa::firstOrCreate([]),
        ];
    }
}
