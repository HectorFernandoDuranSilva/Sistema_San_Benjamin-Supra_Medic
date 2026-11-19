<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleRequest;
use App\Models\Cliente;
use App\Models\ConfiguracionEmpresa;
use App\Models\Producto;
use App\Models\TipoCambio;
use App\Models\Venta;
use App\Services\AuditLogService;
use App\Services\SaleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'buscar' => ['nullable', 'string', 'max:255'],
            'tipo_documento' => ['nullable', 'in:factura,nota_entrega'],
            'nombre_comercial' => ['nullable', 'in:San Benjamin,Supra Medic'],
            'anio' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $ventas = Venta::with('cliente', 'usuario', 'auditorias.usuario')
            ->withSum(['cobros as cobrado' => fn ($query) => $query->where('estado', 'registrado')], 'importe')
            ->withMax(['cobros as fecha_ultimo_cobro' => fn ($query) => $query->where('estado', 'registrado')], 'fecha')
            ->when($filters['buscar'] ?? null, function ($query, $buscar) {
                $buscar = '%' . trim($buscar) . '%';
                $query->where(function ($query) use ($buscar) {
                    $query->where('numero_venta', 'like', $buscar)
                        ->orWhere('factura', 'like', $buscar)
                        ->orWhere('nit_cliente', 'like', $buscar)
                        ->orWhereHas('cliente', fn ($cliente) => $cliente->coincideConBusqueda($buscar));
                });
            })
            ->when(($filters['tipo_documento'] ?? null) === 'factura', fn ($query) => $query
                ->where(fn ($documento) => $documento->whereNull('tipo_documento')->orWhere('tipo_documento', '!=', 'nota_entrega')))
            ->when(($filters['tipo_documento'] ?? null) === 'nota_entrega', fn ($query) => $query->where('tipo_documento', 'nota_entrega'))
            ->when($filters['nombre_comercial'] ?? null, fn ($query, $nombre) => $query->where('nombre_comercial', $nombre))
            ->when($filters['anio'] ?? null, fn ($query, $anio) => $query->whereYear('fecha', $anio))
            ->latest('fecha')
            ->orderByRaw("CASE WHEN numero_venta LIKE 'EXCEL-V-%' THEN CAST(SUBSTR(numero_venta, 9) AS INTEGER) ELSE id END DESC")
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('ventas.index', compact('ventas', 'filters'));
    }

    public function create(Request $request, SaleService $sales, \App\Services\InventoryService $inventory)
    {
        $facturaStockPendiente = $request->boolean('stock_pendiente')
            && ! $request->routeIs('notas.*');
        $tipoDocumento = $request->routeIs('notas.*')
            ? 'nota_entrega'
            : ($facturaStockPendiente || $request->query('tipo_documento') !== 'nota_entrega' ? 'factura' : 'nota_entrega');
        $rate = (float) (TipoCambio::where('estado', 'vigente')->latest('fecha')->latest('id')->value('valor') ?: TipoCambio::ultimoValor());
        $productos = Producto::with(['lotes' => fn ($query) => $query
            ->where('estado', 'activo')
            ->where('cantidad_disponible', '>', 0)
            ->where(fn ($query) => $query
                ->whereNull('fecha_vencimiento')
                ->orWhereDate('fecha_vencimiento', '>=', today()))
            ->orderByRaw('CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END')
            ->orderBy('fecha_vencimiento')
            ->orderBy('id'),
        ])->where('activo', true)->orderBy('nombre')->get();

        return view('ventas.form', [
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(),
            'productos' => $productos,
            'productosVenta' => $productos->map(fn ($p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'marca' => $p->marca,
                'procedencia' => $p->procedencia,
                'medida' => $p->medida,
                'stock' => $p->stock_actual,
                'stock_disponible' => $inventory->stockDisponibleParaVenta($p),
                'price' => $p->precio_venta,
                'cost' => $p->costo_referencial,
                'suggested' => app(\App\Services\ProductPricingService::class)->suggestedReplacementPrice($p, $rate),
                'lotes' => $this->lotesConsolidados($p)->map(fn ($lote) => [
                    'id' => $lote['id'],
                    'codigo' => $lote['codigo'] ?: 'sin lote',
                    'fecha_vencimiento' => $lote['fecha_vencimiento'],
                    'fecha_vencimiento_label' => $lote['fecha_vencimiento'] ? \Carbon\Carbon::parse($lote['fecha_vencimiento'])->format('d/m/Y') : 'sin vencimiento',
                    'cantidad_disponible' => $lote['cantidad_disponible'],
                ])->values(),
            ])->values(),
            'commercial' => session('commercial_context'),
            'tipoDocumento' => $tipoDocumento,
            'facturaStockPendiente' => $facturaStockPendiente,
            'esModuloNotas' => $request->routeIs('notas.*'),
            'siguienteNumero' => $sales->siguienteNumero(),
        ]);
    }

    public function store(SaleRequest $request, SaleService $service)
    {
        try {
            $data = $request->validated();
            if ($request->routeIs('notas.store')) {
                $data['tipo_documento'] = 'nota_entrega';
            }
            $venta = $service->registrar($data, $request->user()->id);
            if ($venta->permitir_salida_sin_stock) {
                app(AuditLogService::class)->record(
                    $request,
                    $venta->tipo_documento === 'nota_entrega' ? 'registrar_entrega_sin_stock' : 'registrar_factura_stock_pendiente',
                    $venta->tipo_documento === 'nota_entrega' ? 'notas_entrega' : 'ventas',
                    $venta,
                    null,
                    $venta->only(['numero_venta', 'motivo_salida_sin_stock']),
                    $venta->tipo_documento === 'nota_entrega'
                        ? 'Nota de entrega registrada con faltante de inventario.'
                        : 'Factura registrada con stock pendiente.'
                );
            }
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['venta' => $e->getMessage()])->withInput();
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000' || str_contains(strtolower($e->getMessage()), 'unique')) {
                return back()->withErrors(['venta' => 'El consecutivo fue tomado por otra operación. Recargue la pantalla y vuelva a registrar la venta.'])->withInput();
            }

            throw $e;
        }

        $esNota = $venta->tipo_documento === 'nota_entrega';
        return redirect()->route($esNota ? 'notas.show' : 'ventas.show', $venta)
            ->with('ok', $esNota ? 'Nota de entrega registrada.' : 'Venta registrada.');
    }

    public function show(Venta $venta)
    {
        if (request()->routeIs('notas.show')) {
            abort_unless($venta->tipo_documento === 'nota_entrega', 404);
        }
        $cuotasDisponibles = Schema::hasTable('venta_cuotas');
        $relations = ['cliente', 'detalles.producto', 'detalles.lotes.lote', 'usuario', 'auditorias.usuario'];
        if ($cuotasDisponibles) {
            $relations[] = 'cuotas.cobros';
        }

        return view('ventas.show', [
            'venta' => $venta->load($relations),
            'cuotasDisponibles' => $cuotasDisponibles,
        ]);
    }

    public function editHistory(Venta $venta)
    {
        return $this->editProducts($venta, false);
    }

    public function updateHistory(Request $request, Venta $venta, SaleService $service)
    {
        $data = $request->validate([
            'nombre_comercial' => ['required', 'in:San Benjamin,Supra Medic'],
            'tipo_documento' => ['nullable', 'in:factura,nota_entrega'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'fecha' => ['required', 'date'],
            'factura' => ['nullable', 'string', 'max:255'],
            'nit_cliente' => ['nullable', 'string', 'max:255'],
            'lista_precios' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string'],
            'descuento_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tipo_tarjeta' => ['nullable', 'in:genrui,getein'],
            'destinatario_tarjeta' => ['nullable', 'string', 'max:255'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.producto_id' => ['required_with:items', 'exists:productos,id'],
            'items.*.cantidad' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.precio_unitario' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'items.*.impuesto' => ['nullable', 'numeric', 'min:0'],
            'items.*.costo_envio' => ['nullable', 'numeric', 'min:0'],
            'items.*.otros_costos' => ['nullable', 'numeric', 'min:0'],
            'items.*.inventario_lote_id' => ['nullable', 'integer', 'exists:inventario_lotes,id'],
            'items.*.codigo_lote_manual' => ['nullable', 'string', 'max:255'],
            'items.*.fecha_vencimiento_manual' => ['nullable', 'date'],
            'items.*.tarjeta_magnetica' => ['nullable', 'boolean'],
        ]);

        $items = $data['items'] ?? null;
        unset($data['items']);
        $before = $venta->load('detalles.lotes')->toArray();

        try {
            DB::transaction(function () use ($venta, $data, $items, $request, $before, $service) {
                $venta->update($data);
                if ($items !== null) {
                    $service->actualizarProductos($venta, $items, $request->user()->id);
                }
                $actualizada = $venta->fresh('detalles.lotes');
                app(AuditLogService::class)->record(
                    $request,
                    $items === null ? 'actualizo' : 'actualizo_documento',
                    'ventas',
                    $venta,
                    $before,
                    $actualizada->toArray(),
                    $items === null
                        ? 'Edición de cabecera de documento'
                        : 'Edición integral de documento, productos, lotes e inventario'
                );
            });
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['venta' => $e->getMessage()])->withInput();
        }

        return redirect()->route($venta->tipo_documento === 'nota_entrega' ? 'notas.show' : 'ventas.show', $venta)
            ->with('ok', $items === null
                ? ($venta->tipo_documento === 'nota_entrega' ? 'Nota de entrega actualizada.' : 'Cabecera de venta actualizada.')
                : ($venta->tipo_documento === 'nota_entrega' ? 'Nota de entrega actualizada correctamente.' : 'Venta actualizada correctamente.'));
    }

    public function editProducts(Venta $venta, bool $enforced = true)
    {
        $puedeEditarProductos = $venta->estado === 'registrada'
            && ! $venta->cobros()->where('estado', 'registrado')->exists()
            && ! $venta->cuotas()->exists();
        if ($enforced) {
            abort_unless($venta->estado === 'registrada', 422, 'Solo se pueden editar documentos registrados.');
            abort_unless($puedeEditarProductos, 422, 'Regularice los cobros o cuotas antes de editar los productos.');
        }

        $productoIdsActuales = $venta->detalles()->pluck('producto_id')->filter()->values();
        $rate = (float) (TipoCambio::where('estado', 'vigente')->latest('fecha')->latest('id')->value('valor') ?: TipoCambio::ultimoValor());
        $productos = Producto::with(['lotes' => fn ($query) => $query
            ->where('estado', 'activo')
            ->orderByRaw('CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END')
            ->orderBy('fecha_vencimiento')
            ->orderBy('id'),
        ])->where('activo', true)
            ->orWhereIn('id', $productoIdsActuales)
            ->orderBy('nombre')
            ->get();
        $venta->load('detalles.producto', 'detalles.lotes.lote');
        $itemsEditor = $venta->detalles->map(function ($detalle) {
            $asignacion = $detalle->lotes->first();

            return [
                'producto_id' => $detalle->producto_id,
                'cantidad' => $detalle->cantidad,
                'precio_unitario' => $detalle->precio_unitario,
                'descuento' => $detalle->descuento,
                'impuesto' => $detalle->impuesto,
                'costo_envio' => $detalle->costo_envio,
                'otros_costos' => $detalle->otros_costos,
                'inventario_lote_id' => $asignacion?->inventario_lote_id,
                'codigo_lote_manual' => $detalle->codigo_lote_manual,
                'fecha_vencimiento_manual' => $detalle->fecha_vencimiento_manual?->format('Y-m-d'),
                'tarjeta_magnetica' => $detalle->tarjeta_magnetica,
            ];
        })->values()->all();

        return view('ventas.edit-products', [
            'venta' => $venta,
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(),
            'puedeEditarProductos' => $puedeEditarProductos,
            'productos' => $productos,
            'productosEditor' => $productos->map(fn ($producto) => [
                'id' => $producto->id,
                'codigo' => $producto->codigo,
                'nombre' => $producto->nombre,
                'precio_venta' => $producto->precio_venta,
                'costo_referencial' => $producto->costo_referencial,
                'precio_sugerido' => app(\App\Services\ProductPricingService::class)->suggestedReplacementPrice($producto, $rate),
                'lotes' => $this->lotesConsolidados($producto)->map(fn ($lote) => [
                    'id' => $lote['id'],
                    'codigo' => $lote['codigo'] ?: 'Sin lote',
                    'vencimiento' => $lote['fecha_vencimiento'],
                    'disponible' => $lote['cantidad_disponible'],
                ])->values(),
            ])->values(),
            'itemsEditor' => $itemsEditor,
        ]);
    }

    public function updateProducts(Request $request, Venta $venta, SaleService $service)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'items.*.impuesto' => ['nullable', 'numeric', 'min:0'],
            'items.*.costo_envio' => ['nullable', 'numeric', 'min:0'],
            'items.*.otros_costos' => ['nullable', 'numeric', 'min:0'],
            'items.*.inventario_lote_id' => ['nullable', 'integer', 'exists:inventario_lotes,id'],
            'items.*.codigo_lote_manual' => ['nullable', 'string', 'max:255'],
            'items.*.fecha_vencimiento_manual' => ['nullable', 'date'],
            'items.*.tarjeta_magnetica' => ['nullable', 'boolean'],
        ]);

        try {
            $before = $venta->load('detalles')->toArray();
            $service->actualizarProductos($venta, $data['items'], $request->user()->id);
            app(AuditLogService::class)->record($request, 'actualizo_productos', 'ventas', $venta, $before, $venta->fresh('detalles')->toArray(), 'Edición de productos, cantidades e inventario del documento');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        return redirect()->route($venta->tipo_documento === 'nota_entrega' ? 'notas.show' : 'ventas.show', $venta)->with('ok', 'Productos del documento actualizados correctamente.');
    }

    public function anular(Request $request, Venta $venta, SaleService $service)
    {
        $before = $venta->only(['estado']);
        try {
            $service->anular($venta, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['venta' => $e->getMessage()]);
        }
        app(AuditLogService::class)->record(
            $request,
            'anulo',
            'ventas',
            $venta,
            $before,
            $venta->fresh()->only(['estado']),
            'Anulacion de venta y devolucion de stock'
        );

        return back()->with('ok', $venta->tipo_documento === 'nota_entrega' ? 'Nota de entrega anulada y stock devuelto.' : 'Venta anulada y stock devuelto.');
    }

    public function cerrarStockPendiente(Request $request, Venta $venta, AuditLogService $audit)
    {
        $data = $request->validate([
            'totp_code' => ['required', 'string', 'max:32'],
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'ventas', $venta, 'Intento de cerrar stock pendiente sin entrada')) {
            return back()->withErrors(['totp_code' => $error]);
        }

        try {
            DB::transaction(function () use ($request, $venta, $audit, $data): void {
                $venta = Venta::with('detalles')->whereKey($venta->id)->lockForUpdate()->firstOrFail();
                $pendientes = $venta->detalles->filter(fn ($detalle) => (float) $detalle->faltante_stock > 0);
                if (! $venta->permitir_salida_sin_stock || $pendientes->isEmpty()) {
                    throw new InvalidArgumentException('Este documento no tiene stock pendiente para cerrar.');
                }

                $before = $pendientes->mapWithKeys(fn ($detalle) => [$detalle->id => (float) $detalle->faltante_stock])->all();
                foreach ($pendientes as $detalle) {
                    $detalle->update(['faltante_stock' => 0]);
                }
                $audit->record(
                    $request,
                    'cerro_stock_pendiente',
                    'ventas',
                    $venta,
                    ['faltante_stock' => $before],
                    ['faltante_stock' => []],
                    'Stock pendiente cerrado sin entrada de inventario: '.$data['motivo']
                );
            });
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['venta' => $e->getMessage()]);
        }

        return back()->with('ok', 'Stock pendiente cerrado con auditoría y sin modificar el inventario.');
    }

    public function convertirAFactura(Request $request, Venta $venta)
    {
        $data = $request->validate([
            'factura' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            DB::transaction(function () use ($request, $venta, $data): void {
                $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
                if ($venta->tipo_documento !== 'nota_entrega') {
                    throw new InvalidArgumentException('Solo las notas de entrega pueden convertirse en factura.');
                }
                if ($venta->estado !== 'registrada') {
                    throw new InvalidArgumentException('Solo se pueden convertir notas de entrega registradas.');
                }

                $before = $venta->only(['tipo_documento', 'factura']);
                $venta->update([
                    'tipo_documento' => 'factura',
                    'factura' => trim((string) ($data['factura'] ?? '')) ?: $venta->factura,
                ]);
                app(AuditLogService::class)->record(
                    $request,
                    'convirtio_a_factura',
                    'ventas',
                    $venta,
                    $before,
                    $venta->only(['tipo_documento', 'factura']),
                    'Conversión de nota de entrega a factura sin duplicar inventario ni movimientos.'
                );
            });
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['venta' => $e->getMessage()]);
        }

        return redirect()->route('ventas.show', $venta)->with('ok', 'Nota de entrega convertida en factura sin duplicar el movimiento de inventario.');
    }

    public function destroy(Request $request, Venta $venta, SaleService $service)
    {
        $request->validate(['totp_code' => ['required', 'string', 'max:32']]);
        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'ventas', $venta, 'Intento de eliminar definitivamente una venta')) {
            return back()->withErrors(['totp_code' => $error]);
        }

        try {
            $before = $service->eliminarDefinitivamente($venta);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['venta' => $e->getMessage()]);
        }

        app(AuditLogService::class)->record(
            $request,
            'elimino_definitivamente',
            'ventas',
            $venta,
            $before,
            null,
            'Eliminación definitiva de documento y reconstrucción de inventario.'
        );

        return redirect()->route('ventas.index')->with('ok', 'Documento eliminado definitivamente. El correlativo se reutilizará solo si era el último número emitido.');
    }

    public function recibo(Venta $venta)
    {
        return Pdf::loadView('recibos.venta', $this->receiptData($venta) + ['isPdf' => true])
            ->setPaper('letter')
            ->stream("recibo-venta-{$venta->numero_venta}.pdf");
    }

    public function pdf(Venta $venta)
    {
        return Pdf::loadView('recibos.venta', $this->receiptData($venta) + ['isPdf' => true])
            ->setPaper('letter')
            ->download("recibo-venta-{$venta->numero_venta}.pdf");
    }

    public function reciboLotesPrueba(Venta $venta)
    {
        return Pdf::loadView('recibos.venta-lotes-test', $this->receiptData($venta) + ['isPdf' => true])
            ->setPaper('letter')
            ->stream("recibo-venta-lotes-prueba-{$venta->numero_venta}.pdf");
    }

    public function pdfLotesPrueba(Venta $venta)
    {
        return Pdf::loadView('recibos.venta-lotes-test', $this->receiptData($venta) + ['isPdf' => true])
            ->setPaper('letter')
            ->download("recibo-venta-lotes-prueba-{$venta->numero_venta}.pdf");
    }

    private function receiptData(Venta $venta): array
    {
        return [
            'venta' => $venta->load('cliente', 'detalles.producto', 'detalles.lotes.lote', 'usuario'),
            'empresa' => ConfiguracionEmpresa::firstOrCreate([]),
        ];
    }

    /**
     * A product can have several historical entries with the same lot code.
     * The operator must see one usable lot with the combined balance, while
     * retaining the first id so the inventory service can consume the group
     * and continue with the remaining matching entries automatically.
     */
    private function lotesConsolidados(Producto $producto): \Illuminate\Support\Collection
    {
        return $producto->lotes
            ->groupBy(fn ($lote) => mb_strtoupper(trim((string) ($lote->codigo_lote ?: 'SIN LOTE'))) . '|' . ($lote->fecha_vencimiento?->format('Y-m-d') ?: 'SIN FECHA'))
            ->map(function ($lotes) {
                // A consolidated lot can contain an exhausted historical row
                // followed by a row with the current balance. The id sent to
                // the sale form must point to a usable row, not merely the
                // oldest row in the group.
                $primero = $lotes->first(fn ($lote): bool => (float) $lote->cantidad_disponible > 0)
                    ?? $lotes->first();

                return [
                    'id' => $primero->id,
                    'codigo' => $primero->codigo_lote,
                    'fecha_vencimiento' => $primero->fecha_vencimiento?->format('Y-m-d'),
                    'cantidad_disponible' => round((float) $lotes->sum('cantidad_disponible'), 2),
                ];
            })
            ->values();
    }
}
