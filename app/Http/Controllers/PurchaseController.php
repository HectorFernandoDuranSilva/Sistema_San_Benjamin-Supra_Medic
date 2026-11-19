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
use Illuminate\Database\QueryException;
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

        $compras = Compra::with('proveedor', 'usuario', 'auditorias.usuario')
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
            $data = $request->validated();
            $compra = $service->registrar($data, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['compra' => $e->getMessage()])->withInput();
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000' || str_contains(strtolower($e->getMessage()), 'unique')) {
                return back()->withErrors(['compra' => 'El consecutivo fue tomado por otra operación. Recargue la pantalla y vuelva a registrar la compra.'])->withInput();
            }

            throw $e;
        }

        return redirect()->route('compras.show', $compra)->with('ok', 'Compra registrada.');
    }

    public function show(Compra $compra)
    {
        return view('compras.show', ['compra' => $compra->load('proveedor', 'detalles.producto', 'detalles.lote', 'gastos', 'usuario', 'auditorias.usuario', 'pagos.cuota', 'cuotas.pagos')]);
    }

    public function anular(Request $request, Compra $compra, PurchaseService $service)
    {
        $before = $compra->only(['estado']);
        try {
            $service->anular($compra, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['compra' => $e->getMessage()]);
        }

        app(AuditLogService::class)->record(
            $request,
            'anulo',
            'compras',
            $compra,
            $before,
            $compra->fresh()->only(['estado']),
            'Anulacion de compra y retiro de stock ingresado'
        );

        return back()->with('ok', 'Compra anulada y stock retirado del inventario.');
    }

    public function destroy(Request $request, Compra $compra, PurchaseService $service)
    {
        $request->validate(['totp_code' => ['required', 'string', 'max:32']]);
        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'compras', $compra, 'Intento de eliminar definitivamente una compra')) {
            return back()->withErrors(['totp_code' => $error]);
        }

        try {
            $before = $service->eliminarDefinitivamente($compra);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['compra' => $e->getMessage()]);
        }

        app(AuditLogService::class)->record(
            $request,
            'elimino_definitivamente',
            'compras',
            $compra,
            $before,
            null,
            'Eliminacion definitiva de compra y reconstruccion de inventario.'
        );

        return redirect()->route('compras.index')->with('ok', 'Compra eliminada definitivamente.');
    }

    public function storePayment(Request $request, Compra $compra, AuditLogService $audit)
    {
        $data = $request->validate([
            'token' => ['required', 'uuid'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'importe' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999'],
            'medio' => ['required', 'in:efectivo,transferencia,deposito'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'cuota_id' => ['nullable', 'integer', 'exists:compra_cuotas,id'],
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

            $cuotas = $compra->cuotas()->with('pagos')->lockForUpdate()->get();
            if ($cuotas->isNotEmpty()) {
                $cuota = $cuotas->firstWhere('id', (int) ($data['cuota_id'] ?? 0));
                if (! $cuota) {
                    return back()->withErrors(['cuota_id' => 'Seleccione la cuota a la que corresponde este pago.']);
                }
                $saldoCuota = round((float) $cuota->importe * 100) - round((float) $cuota->pagos()->where('estado', 'registrado')->sum('importe') * 100);
                if (round((float) $data['importe'] * 100) > $saldoCuota) {
                    return back()->withErrors(['importe' => 'El importe supera el saldo de la cuota seleccionada.']);
                }
            } elseif (! empty($data['cuota_id'])) {
                return back()->withErrors(['cuota_id' => 'Esta compra no tiene un calendario de cuotas.']);
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
        return $this->editProducts($compra, false);
    }

    public function updateHistory(Request $request, Compra $compra)
    {
        $data = $request->validate([
            'nombre_comercial' => ['required', 'in:San Benjamin,Supra Medic'],
            'tipo_documento' => ['nullable', 'in:factura,nota_entrega'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'fecha' => ['required', 'date'],
            'factura' => ['nullable', 'string', 'max:255'],
            'nit_proveedor' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string'],
        ]);

        $before = $compra->only(['nombre_comercial', 'tipo_documento', 'proveedor_id', 'fecha', 'factura', 'nit_proveedor', 'observacion']);
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

    public function editProducts(Compra $compra, bool $enforced = true)
    {
        $puedeEditarProductos = $compra->estado === 'registrada'
            && ! $compra->pagos()->where('estado', 'registrado')->exists()
            && ! $compra->cuotas()->exists();
        if ($enforced) {
            abort_unless($compra->estado === 'registrada', 422, 'Solo se pueden editar compras registradas.');
            abort_unless($puedeEditarProductos, 422, 'Regularice los pagos o cuotas antes de editar los productos.');
        }

        $productos = Producto::where('activo', true)->orderBy('nombre')->get();

        $compra->load('detalles.producto', 'detalles.lote');
        $itemsEditor = $compra->detalles->map(fn ($detalle) => [
            'producto_id' => $detalle->producto_id,
            'cantidad' => $detalle->cantidad,
            'costo_unitario' => $detalle->costo_unitario,
            'porcentaje_utilidad' => $detalle->porcentaje_utilidad,
            'descuento_fijo' => $detalle->descuento_fijo,
            'impuesto' => $detalle->impuesto,
            'tipo_lote' => $detalle->fecha_vencimiento ? 'con_lote' : ($detalle->codigo_lote ? 'sin_vencimiento' : 'sin_lote'),
            'codigo_lote' => $detalle->codigo_lote,
            'fecha_vencimiento' => $detalle->fecha_vencimiento?->format('Y-m-d'),
        ])->values()->all();

        return view('compras.edit-products', [
            'compra' => $compra,
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(),
            'puedeEditarProductos' => $puedeEditarProductos,
            'productos' => $productos,
            'productosEditor' => $productos->map(fn ($producto) => [
                'id' => $producto->id,
                'codigo' => $producto->codigo,
                'nombre' => $producto->nombre,
            ])->values(),
            'itemsEditor' => $itemsEditor,
        ]);
    }

    public function updateProducts(Request $request, Compra $compra, PurchaseService $service)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0'],
            'items.*.costo_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.porcentaje_utilidad' => ['required', 'numeric', 'min:25', 'max:30'],
            'items.*.descuento_fijo' => ['nullable', 'numeric', 'min:0'],
            'items.*.impuesto' => ['nullable', 'numeric', 'min:0'],
            'items.*.tipo_lote' => ['nullable', 'in:con_lote,sin_vencimiento,sin_lote'],
            'items.*.codigo_lote' => ['nullable', 'string', 'max:255'],
            'items.*.fecha_vencimiento' => ['nullable', 'date'],
        ]);

        try {
            $before = $compra->load('detalles')->toArray();
            $service->actualizarProductos($compra, $data['items'], $request->user()->id);
            app(AuditLogService::class)->record($request, 'actualizo_productos', 'compras', $compra, $before, $compra->fresh('detalles')->toArray(), 'Edición de productos, cantidades e inventario de la compra');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        return redirect()->route('compras.show', $compra)->with('ok', 'Productos de la compra actualizados correctamente.');
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
            'compra' => $compra->load('proveedor', 'detalles.producto', 'gastos', 'usuario'),
            'empresa' => ConfiguracionEmpresa::firstOrCreate([]),
        ];
    }
}
