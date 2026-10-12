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

        $ventas = Venta::with('cliente', 'usuario')
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
                'lotes' => $p->lotes->map(fn ($lote) => [
                    'id' => $lote->id,
                    'codigo' => $lote->codigo_lote ?: 'sin lote',
                    'fecha_vencimiento' => $lote->fecha_vencimiento?->format('Y-m-d'),
                    'fecha_vencimiento_label' => $lote->fecha_vencimiento?->format('d/m/Y') ?? 'sin vencimiento',
                    'cantidad_disponible' => $lote->cantidad_disponible,
                ])->values(),
            ])->values(),
            'commercial' => session('commercial_context'),
            'tipoDocumento' => $request->routeIs('notas.*') || $request->query('tipo_documento') === 'nota_entrega' ? 'nota_entrega' : 'factura',
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
        $relations = ['cliente', 'detalles.producto', 'detalles.lotes.lote', 'usuario'];
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
        return view('ventas.edit-history', [
            'venta' => $venta->load('cliente'),
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function updateHistory(Request $request, Venta $venta)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'totp_code' => ['required', 'string', 'max:32'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'tipo_documento' => ['nullable', 'in:factura,nota_entrega'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'fecha' => ['required', 'date'],
            'factura' => ['nullable', 'string', 'max:255'],
            'nit_cliente' => ['nullable', 'string', 'max:255'],
            'lista_precios' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string'],
        ]);

        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'ventas', $venta, 'Intento de editar historial de venta')) {
            return back()->withErrors(['totp_code' => $error])->withInput();
        }

        $before = $venta->only(['nombre_comercial', 'tipo_documento', 'cliente_id', 'fecha', 'factura', 'nit_cliente', 'lista_precios', 'observacion']);
        unset($data['totp_code']);
        DB::transaction(function () use ($venta, $data, $request, $before) {
            $venta->update($data);
            app(AuditLogService::class)->record(
                $request,
                'actualizo',
                'ventas',
                $venta,
                $before,
                $venta->only(array_keys($before)),
                'Edición segura de cabecera de documento'
            );
        });

        return redirect()->route($venta->tipo_documento === 'nota_entrega' ? 'notas.show' : 'ventas.show', $venta)
            ->with('ok', $venta->tipo_documento === 'nota_entrega' ? 'Nota de entrega actualizada sin modificar inventario.' : 'Cabecera de venta actualizada sin modificar inventario.');
    }

    public function anular(Request $request, Venta $venta, SaleService $service)
    {
        $request->validate(['totp_code' => ['required', 'string', 'max:32']]);
        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'ventas', $venta, 'Intento de anular venta')) {
            return back()->withErrors(['totp_code' => $error]);
        }
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
}
