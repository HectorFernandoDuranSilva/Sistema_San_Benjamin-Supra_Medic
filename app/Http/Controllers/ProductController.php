<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\TipoCambio;
use App\Services\AuditLogService;
use App\Services\InventoryService;
use App\Services\ProductPricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductPricingService $pricing,
        private readonly InventoryService $inventory
    )
    {
    }

    public function index(Request $request)
    {
        $q = $request->string('q');
        $estado = $request->string('estado', 'activos')->toString();
        $stockEstado = $request->string('stock', 'todos')->toString();
        $tipoCambioActual = TipoCambio::where('estado', 'vigente')->latest('fecha')->latest('id')->first();
        $tipoCambioValor = $tipoCambioActual ? (float) $tipoCambioActual->valor : TipoCambio::ultimoValor();
        $query = $this->productQuery($request, $q, $estado, $stockEstado);
        $productos = $query
            ->paginate(20)
            ->withQueryString();

        $productos->getCollection()->each(function (Producto $producto) use ($tipoCambioValor): void {
            $producto->setAttribute('costo_reposicion_actual', $this->pricing->replacementCost($producto, $tipoCambioValor));
            $producto->setAttribute('precio_sugerido_reposicion', $this->pricing->suggestedReplacementPrice($producto, $tipoCambioValor));
        });

        $categorias = Categoria::where('activo', true)->orderBy('nombre')->get();

        return view('productos.index', compact('productos', 'categorias', 'estado', 'stockEstado', 'tipoCambioActual', 'tipoCambioValor'));
    }

    public function exportStock(Request $request)
    {
        $q = $request->string('q');
        $estado = $request->string('estado', 'activos')->toString();
        $stockEstado = $request->string('stock', 'todos')->toString();
        $productos = $this->productQuery($request, $q, $estado, $stockEstado)->get();
        $productos->load('categoria');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stock');
        $sheet->setCellValue('A1', 'Listado de stock');
        $sheet->setCellValue('A2', 'Filtros');
        $sheet->setCellValue('B2', 'Categoría: '.($request->filled('categoria_id') ? ($productos->first()?->categoria?->nombre ?? 'Seleccionada') : 'Todas').
            ' | Stock: '.match ($stockEstado) { 'con_stock' => 'Con stock', 'sin_stock' => 'Sin stock (0 o negativo)', default => 'Todos' });
        $sheet->getStyle('A1:B1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:B2')->getFont()->setItalic(true);

        $groups = $productos->groupBy(fn (Producto $producto) => $producto->categoria?->nombre ?: 'Sin categoría')->values();
        if ($groups->isEmpty()) {
            $groups = collect([collect()]);
        }

        foreach ($groups->chunk(2) as $rowGroup) {
            $startRow = $sheet->getHighestRow() + 2;
            foreach ($rowGroup->values() as $position => $items) {
                $startColumn = $position === 0 ? 1 : 4;
                $categoryName = $items->first()?->categoria?->nombre ?: 'Sin categoría';
                $productColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColumn);
                $stockColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColumn + 1);
                $sheet->setCellValue($productColumn.$startRow, mb_strtoupper($categoryName));
                $sheet->setCellValue($stockColumn.$startRow, 'EXISTENCIAS');
                $sheet->getStyle($productColumn.$startRow.':'.$stockColumn.$startRow)->getFont()->setBold(true);
                $sheet->getStyle($productColumn.$startRow.':'.$stockColumn.$startRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9EAD3');

                foreach ($items as $offset => $producto) {
                    $row = $startRow + $offset + 1;
                    $sheet->setCellValue($productColumn.$row, $producto->nombre);
                    $sheet->setCellValue($stockColumn.$row, (float) $producto->stock_actual);
                    $sheet->getStyle($stockColumn.$row)->getNumberFormat()->setFormatCode('0.00');
                }

                $sheet->getColumnDimension($productColumn)->setWidth(48);
                $sheet->getColumnDimension($stockColumn)->setWidth(14);
            }
        }

        $sheet->freezePane('A4');
        $sheet->getDefaultRowDimension()->setRowHeight(18);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'stock-productos.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function productQuery(Request $request, $q, string $estado, string $stockEstado)
    {
        $latestStock = '(select stock_nuevo from movimientos_inventario as stock_movimientos where stock_movimientos.producto_id = productos.id order by stock_movimientos.fecha_movimiento desc, stock_movimientos.id desc limit 1)';

        return Producto::with('categoria')
            ->when($q->isNotEmpty(), fn ($query) => $query->where(function ($query) use ($q) {
                $query->where('codigo', 'like', "%{$q}%")->orWhere('nombre', 'like', "%{$q}%");
            }))
            ->when($request->filled('categoria_id'), fn ($query) => $query->where('categoria_id', $request->integer('categoria_id')))
            ->when($estado === 'activos', fn ($query) => $query->where('activo', true))
            ->when($estado === 'inactivos', fn ($query) => $query->where('activo', false))
            ->when($stockEstado === 'con_stock', fn ($query) => $query->whereRaw("coalesce({$latestStock}, 0) > 0"))
            ->when($stockEstado === 'sin_stock', fn ($query) => $query->whereRaw("coalesce({$latestStock}, 0) <= 0"))
            ->orderBy('nombre');
    }

    public function create()
    {
        $tipoCambioActual = TipoCambio::where('estado', 'vigente')->latest('fecha')->latest('id')->first();

        return view('productos.form', [
            'producto' => new Producto(['activo' => true, 'moneda_costo' => 'usd', 'tipo_cambio' => $tipoCambioActual ? (float) $tipoCambioActual->valor : TipoCambio::ultimoValor(), 'porcentaje_utilidad' => 27]),
            'categorias' => Categoria::orderBy('nombre')->get(),
            'tipoCambioActual' => $tipoCambioActual,
            'lotes' => collect(),
        ]);
    }

    public function store(ProductRequest $request)
    {
        DB::transaction(function () use ($request): void {
            $producto = Producto::create($this->productData($request) + ['activo' => $request->boolean('activo', true)]);
            $stockInicial = (float) $request->input('stock_inicial', 0);

            if ($stockInicial > 0) {
                $this->inventory->registrarMovimiento(
                    $producto,
                    'entrada',
                    $stockInicial,
                    'producto',
                    $producto->id,
                    $request->user()->id,
                    'Stock inicial al crear producto',
                    (float) $producto->costo_referencial > 0 ? (float) $producto->costo_referencial : null
                );

                $this->inventory->registrarLoteEntrada(
                    $producto,
                    $stockInicial,
                    null,
                    null,
                    (float) $producto->costo_referencial,
                    $request->input('fecha_vencimiento') ?: null,
                    trim((string) $request->input('codigo_lote')) ?: null,
                    $request->user()->id,
                    'Lote inicial al crear producto'
                );
            }
        });

        return redirect()->route('productos.index')->with('ok', 'Producto creado.');
    }

    public function show(Producto $producto)
    {
        $rate = (float) (TipoCambio::where('estado', 'vigente')->latest('fecha')->latest('id')->value('valor') ?: TipoCambio::ultimoValor());
        $producto->setAttribute('costo_reposicion_actual', $this->pricing->replacementCost($producto, $rate));
        $producto->setAttribute('precio_sugerido_reposicion', $this->pricing->suggestedReplacementPrice($producto, $rate));

        return view('productos.show', [
            'producto' => $producto->load('categoria'),
            'lotes' => $this->productBatches($producto),
            'comprasHistoricas' => $producto->detalleCompras()->with('compra.proveedor')->latest('id')->paginate(15),
        ]);
    }

    public function approvePrice(Request $request, Producto $producto)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $data = $request->validate([
            'base' => ['required', 'in:promedio,reposicion'],
            'precio_esperado' => ['required', 'numeric', 'gt:0'],
        ]);
        return DB::transaction(function () use ($request, $producto, $data) {
            $producto = Producto::whereKey($producto->id)->lockForUpdate()->firstOrFail();
            $rate = (float) (TipoCambio::where('estado', 'vigente')->latest('fecha')->latest('id')->value('valor') ?: TipoCambio::ultimoValor());
            $precio = $data['base'] === 'promedio' ? $producto->precio_sugerido
                : $this->pricing->suggestedReplacementPrice($producto, $rate);
            if (!$precio || abs($precio - (float) $data['precio_esperado']) > 0.001) {
                return back()->withErrors(['precio_esperado' => 'El precio sugerido cambió o no tiene una base válida. Revise el producto antes de confirmar.']);
            }
            $before = $producto->only('precio_venta');
            $producto->update(['precio_venta' => $precio]);
            app(AuditLogService::class)->record($request, 'aprobo_precio', 'productos', $producto, $before, $producto->only('precio_venta'), 'Precio aprobado sobre ' . $data['base']);
            return back()->with('ok', 'Precio de venta confirmado. Los documentos anteriores conservan sus importes.');
        });
    }

    public function edit(Producto $producto)
    {
        $tipoCambioActual = TipoCambio::where('estado', 'vigente')->latest('fecha')->latest('id')->first();
        $rate = $tipoCambioActual ? (float) $tipoCambioActual->valor : TipoCambio::ultimoValor();
        $producto->tipo_cambio = $rate;

        if (($producto->moneda_costo ?? 'usd') === 'usd' && (float) $producto->precio_ingreso_usd > 0) {
            $prices = $this->pricing->calculate(
                (float) $producto->precio_ingreso_usd,
                $rate,
                (float) $producto->porcentaje_utilidad
            );

            $producto->costo_referencial = $prices['costo_referencial'];
        }

        return view('productos.form', [
            'producto' => $producto,
            'categorias' => Categoria::orderBy('nombre')->get(),
            'tipoCambioActual' => $tipoCambioActual,
            'lotes' => $this->productBatches($producto),
        ]);
    }

    public function update(ProductRequest $request, Producto $producto)
    {
        $before = $producto->only(['codigo', 'nombre', 'marca', 'procedencia', 'categoria_id', 'medida', 'ubicacion', 'moneda_costo', 'precio_ingreso_usd', 'tipo_cambio', 'porcentaje_utilidad', 'costo_referencial', 'precio_venta', 'stock_minimo', 'activo', 'descripcion']);
        $producto->update($this->productData($request) + ['activo' => $request->boolean('activo')]);
        app(AuditLogService::class)->record(
            $request,
            'actualizo',
            'productos',
            $producto,
            $before,
            $producto->only(array_keys($before)),
            'Actualizacion de producto'
        );

        return redirect()->route('productos.index')->with('ok', 'Producto actualizado.');
    }

    public function storeBatch(Request $request, Producto $producto)
    {
        $data = $request->validate([
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'codigo_lote' => ['nullable', 'string', 'max:255'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'costo_unitario' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $producto, $data): void {
            $cantidad = (float) $data['cantidad'];
            $costoUnitario = (float) ($data['costo_unitario'] ?? $producto->costo_referencial ?? 0);

            $this->inventory->registrarMovimiento(
                $producto,
                'entrada',
                $cantidad,
                'producto_lote',
                $producto->id,
                $request->user()->id,
                $data['observacion'] ?? 'Entrada de lote desde producto',
                $costoUnitario > 0 ? $costoUnitario : null
            );

            $this->inventory->registrarLoteEntrada(
                $producto,
                $cantidad,
                null,
                null,
                $costoUnitario,
                $data['fecha_vencimiento'] ?? null,
                trim((string) ($data['codigo_lote'] ?? '')) ?: null,
                $request->user()->id,
                $data['observacion'] ?? 'Lote agregado desde producto'
            );
        });

        return redirect()->route('productos.show', $producto)->with('ok', 'Lote agregado al producto.');
    }

    public function regularizeShortage(Request $request, Producto $producto)
    {
        $data = $request->validate([
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($request, $producto, $data): void {
                $productoBloqueado = Producto::whereKey($producto->id)->lockForUpdate()->firstOrFail();
                $stockActual = $productoBloqueado->stock_actual;
                $cantidad = (float) $data['cantidad'];
                $faltante = abs(min(0, $stockActual));

                if ($faltante <= 0) {
                    throw new InvalidArgumentException('Este producto no tiene un faltante pendiente de regularizar.');
                }
                if ($cantidad > $faltante + 0.0001) {
                    throw new InvalidArgumentException('La regularización no puede superar el faltante actual de ' . number_format($faltante, 2) . ' unidades.');
                }

                $this->inventory->registrarMovimiento(
                    $productoBloqueado,
                    'ajuste_positivo',
                    $cantidad,
                    'regularizacion_faltante',
                    $productoBloqueado->id,
                    $request->user()->id,
                    trim((string) ($data['observacion'] ?? '')) ?: 'Regularización de faltante de stock'
                );

                app(AuditLogService::class)->record(
                    $request,
                    'regularizo_faltante_stock',
                    'productos',
                    $productoBloqueado,
                    ['stock_anterior' => $stockActual, 'faltante_anterior' => $faltante],
                    ['cantidad_regularizada' => $cantidad, 'stock_nuevo' => $stockActual + $cantidad],
                    'Regularización manual de faltante desde Productos'
                );
            });
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cantidad' => $e->getMessage()])->withInput();
        }

        return redirect()->route('productos.show', $producto)->with('ok', 'Faltante de stock regularizado y registrado en Kardex.');
    }

    public function destroy(Request $request, Producto $producto)
    {
        $request->validate(['totp_code' => ['required', 'string', 'max:32']]);
        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'productos', $producto, 'Intento de eliminar producto')) {
            return $this->redirectAfterDeletion($request)->withErrors(['totp_code' => $error]);
        }

        $hasHistory = $producto->movimientos()->exists()
            || $producto->detalleVentas()->exists()
            || $producto->detalleCompras()->exists();

        if ($hasHistory) {
            $before = $producto->only(['codigo', 'nombre', 'activo']);
            $producto->update(['activo' => false]);
            app(AuditLogService::class)->record(
                $request,
                'desactivo',
                'productos',
                $producto,
                $before,
                $producto->only(array_keys($before)),
                'Producto desactivado por historial relacionado'
            );

            return $this->redirectAfterDeletion($request)
                ->with('ok', 'Producto desactivado porque tiene historial de inventario, compras o ventas.');
        }

        $before = $producto->toArray();
        $producto->delete();
        app(AuditLogService::class)->record(
            $request,
            'elimino',
            'productos',
            $producto,
            $before,
            null,
            'Producto eliminado sin historial relacionado'
        );

        return $this->redirectAfterDeletion($request)->with('ok', 'Producto eliminado.');
    }

    public function forceDestroy(Request $request, Producto $producto)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $request->validate(['totp_code' => ['required', 'string', 'max:32']]);
        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'productos', $producto, 'Intento de eliminación definitiva')) {
            return $this->redirectAfterDeletion($request)->withErrors(['totp_code' => $error]);
        }

        DB::transaction(function () use ($request, $producto): void {
            $before = $producto->toArray();
            $producto->movimientos()->delete();
            $producto->detalleVentas()->delete();
            $producto->detalleCompras()->delete();
            // Remove batches after their sale/purchase references so the
            // product cannot remain visible in the lots and expiry screen.
            $producto->lotes()->delete();
            $producto->delete();
            app(AuditLogService::class)->record(
                $request,
                'elimino_forzado',
                'productos',
                $producto,
                $before,
                null,
                'Producto eliminado definitivamente con Google Authenticator'
            );
        });

        return $this->redirectAfterDeletion($request)->with('ok', 'Producto eliminado definitivamente junto con sus referencias directas.');
    }

    private function redirectAfterDeletion(Request $request): RedirectResponse
    {
        $route = (string) $request->input('return_route', '');

        if (in_array($route, ['inventario.index', 'productos.index'], true)) {
            return redirect()->route($route);
        }

        return redirect()->route('productos.index');
    }

    private function productData(ProductRequest $request): array
    {
        $data = $request->validated();
        unset($data['stock_inicial'], $data['codigo_lote'], $data['fecha_vencimiento']);
        $moneda = $data['moneda_costo'] ?? 'usd';
        $costoBs = (float) ($data['costo_ingreso_bs'] ?? 0);
        unset($data['costo_ingreso_bs']);
        $precioIngreso = (float) ($data['precio_ingreso_usd'] ?? 0);
        $tipoCambio = (float) $data['tipo_cambio'];
        $porcentaje = (float) $data['porcentaje_utilidad'];
        if ($moneda === 'bs') {
            $prices = [
                'costo_referencial' => round($costoBs > 0 ? $costoBs : $precioIngreso * $tipoCambio, 2),
                'precio_venta' => round(($costoBs > 0 ? $costoBs : $precioIngreso * $tipoCambio) * (1 + ($porcentaje / 100)), 2),
            ];
            $data['precio_ingreso_usd'] = 0;
        } else {
            if ($precioIngreso <= 0 && $costoBs > 0) {
                $precioIngreso = round($costoBs / $tipoCambio, 2);
                $data['precio_ingreso_usd'] = $precioIngreso;
            }
            $prices = $this->pricing->calculate($precioIngreso, $tipoCambio, $porcentaje);
        }

        $data['costo_referencial'] = $prices['costo_referencial'];
        $data['precio_venta'] = $prices['precio_venta'];
        if ($request->route('producto')) {
            unset($data['precio_venta']);
        }

        return $data;
    }

    private function productBatches(Producto $producto)
    {
        return $producto->lotes()
            ->withSum('consumos as cantidad_consumida', 'cantidad')
            ->where('estado', 'activo')
            ->orderByRaw('CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END')
            ->orderBy('fecha_vencimiento')
            ->latest('id')
            ->get();
    }
}
