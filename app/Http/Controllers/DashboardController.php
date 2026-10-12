<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\ConfiguracionEmpresa;
use App\Models\InventarioLote;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaCuota;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __invoke()
    {
        if (! session()->has('commercial_context')) {
            return redirect()->route('commercial.select');
        }

        $configuracion = ConfiguracionEmpresa::firstOrCreate([]);
        $diasAlertaVencimiento = max(1, (int) ($configuracion->dias_alerta_vencimiento ?? 30));
        $diasAtrasoCuotas = max(1, (int) ($configuracion->dias_alerta_cuotas ?? 10));
        $ventasHoy = Venta::whereDate('fecha', today())->where('estado', 'registrada')
            ->where(fn ($query) => $query->whereNull('tipo_documento')->orWhere('tipo_documento', '!=', 'nota_entrega'));
        $productos = Producto::with('categoria')->where('activo', true)->get();
        $productosBajoStock = $productos->filter(fn ($producto) => $producto->stock_actual <= (float) $producto->stock_minimo);
        $productosSinPrecio = $productos->filter(fn ($producto) => (float) $producto->precio_venta <= 0);
        $productosSinCategoria = $productos->filter(fn ($producto) => $producto->categoria_id === null);
        $productosStockNegativo = $productos->filter(fn ($producto) => $producto->stock_actual < 0);
        $entregasSinStockPendientes = $this->entregasSinStockPendientes($productosStockNegativo);
        $otrosProductosStockNegativo = $productosStockNegativo->reject(fn ($producto) => $entregasSinStockPendientes->contains('producto_id', $producto->id));
        $productosSinMarcaProcedencia = $productos->filter(fn ($producto) => trim((string) $producto->marca) === '' || trim((string) $producto->procedencia) === '');
        $lotesSinVencimiento = InventarioLote::with('producto')
            ->where('estado', 'activo')
            ->where('cantidad_disponible', '>', 0)
            ->whereNull('fecha_vencimiento')
            ->get();
        $ultimasVentas = Venta::with('cliente', 'usuario')->latest()->take(5)->get();
        $ultimasCompras = Compra::with('proveedor', 'usuario')->latest()->take(5)->get();
        $ultimasOperaciones = $this->ultimasOperaciones($ultimasVentas, $ultimasCompras);
        $movimientoMensual = $this->movimientoMensual();
        $cartera = $this->carteraCuotas($diasAtrasoCuotas);
        $dashboardOpciones = $this->dashboardOpciones($configuracion, auth()->user());

        return view('dashboard', [
            'commercial' => session('commercial_context'),
            'ventasHoy' => (clone $ventasHoy)->count(),
            'totalHoy' => (clone $ventasHoy)->sum('total'),
            'productosBajoStock' => $productosBajoStock,
            'productosStockNegativo' => $productosStockNegativo,
            'alertasDatos' => $this->alertasDatos($productosSinPrecio, $productosSinCategoria, $otrosProductosStockNegativo, $entregasSinStockPendientes, $productosSinMarcaProcedencia, $lotesSinVencimiento),
            'ultimosMovimientos' => MovimientoInventario::with('producto', 'usuario')->latest('fecha_movimiento')->take(4)->get(),
            'ultimasOperaciones' => $ultimasOperaciones,
            'movimientoMensual' => $movimientoMensual,
            'diasAlertaVencimiento' => $diasAlertaVencimiento,
            'lotesPorVencer' => InventarioLote::with('producto')
                ->where('estado', 'activo')
                ->where('cantidad_disponible', '>', 0)
                ->whereNotNull('fecha_vencimiento')
                ->whereDate('fecha_vencimiento', '<=', today()->addDays($diasAlertaVencimiento))
                ->orderBy('fecha_vencimiento')
                ->take(5)
                ->get(),
            'facturacionMensualAlerta' => $this->facturacionMensualAlerta(),
            'cuentasPorCobrar' => $cartera['pendientes'],
            'cuotasConAtraso' => $cartera['atrasadas'],
            'diasAtrasoCuotas' => $diasAtrasoCuotas,
            'dashboardOpciones' => $dashboardOpciones,
        ]);
    }

    private function dashboardOpciones(ConfiguracionEmpresa $configuracion, ?\App\Models\User $usuario): array
    {
        $predeterminadas = [
            'ventas_hoy' => true,
            'total_vendido' => true,
            'stock_bajo' => true,
            'stock_pendiente' => true,
            'movimientos' => true,
            'cuentas_cobrar' => true,
            'alertas_datos' => true,
            'faltantes_stock' => true,
            'vencimientos' => true,
            'cuotas' => true,
            'movimiento_mensual' => true,
            'ultimas_operaciones' => true,
            'facturacion_mensual' => true,
        ];

        return array_merge(
            $predeterminadas,
            $configuracion->dashboard_opciones ?? [],
            data_get($configuracion->dashboard_opciones_por_rol ?? [], $usuario?->role ?? 'operador', []),
            $usuario?->dashboard_preferences ?? [],
        );
    }

    private function carteraCuotas(int $diasAtraso): array
    {
        if (! Schema::hasTable('venta_cuotas')) {
            return [
                'pendientes' => ['cantidad' => 0, 'importe' => 0],
                'atrasadas' => collect(),
            ];
        }

        $cuotas = VentaCuota::with(['venta.cliente', 'cobros'])
            ->whereHas('venta', fn ($query) => $query->where('estado', 'registrada'))
            ->orderBy('fecha_vencimiento')
            ->get()
            ->map(function (VentaCuota $cuota) {
                $pagado = (float) $cuota->cobros->where('estado', 'registrado')->sum('importe');
                $cuota->saldo_pendiente = max(0, (float) $cuota->importe - $pagado);

                return $cuota;
            })
            ->filter(fn (VentaCuota $cuota) => $cuota->saldo_pendiente > 0)
            ->values();

        $documentos = Venta::with('cobros')
            ->where('estado', 'registrada')
            ->get()
            ->map(function (Venta $venta) {
                $pagado = (float) $venta->cobros->where('estado', 'registrado')->sum('importe');
                $venta->saldo_pendiente = max(0, (float) $venta->total - $pagado);
                return $venta;
            })
            ->filter(fn (Venta $venta) => $venta->saldo_pendiente > 0)
            ->values();

        return [
            'pendientes' => [
                'cantidad' => $documentos->count(),
                'importe' => $documentos->sum('saldo_pendiente'),
            ],
            'atrasadas' => $cuotas
                ->filter(fn (VentaCuota $cuota) => $cuota->fecha_vencimiento->lte(today()->subDays($diasAtraso)))
                ->take(5)
                ->values(),
        ];
    }

    private function movimientoMensual(): array
    {
        $desde = today()->startOfMonth();
        $hasta = today()->endOfMonth();
        $ventas = Venta::where('estado', 'registrada')
            ->where(fn ($query) => $query->whereNull('tipo_documento')->orWhere('tipo_documento', '!=', 'nota_entrega'))
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->get()
            ->groupBy(fn ($venta) => $venta->nombre_comercial ?: 'Sin nombre comercial');
        $compras = Compra::where('estado', 'registrada')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->get()
            ->groupBy(fn ($compra) => $compra->nombre_comercial ?: 'Sin nombre comercial');

        $totalVentas = $ventas->flatten(1)->sum('total');
        $totalCompras = $compras->flatten(1)->sum('total');
        $resultado = $totalVentas - $totalCompras;

        return [
            'ventas' => $totalVentas,
            'compras' => $totalCompras,
            'resultado' => $resultado,
            'porcentaje' => $totalVentas > 0 ? min(100, max(0, round(($resultado / $totalVentas) * 100))) : 0,
        ];
    }

    private function facturacionMensualAlerta(): ?array
    {
        $hoy = today();

        if ((int) $hoy->day !== 20 && ! $hoy->isLastOfMonth()) {
            return null;
        }

        $desde = $hoy->copy()->startOfMonth();
        $hasta = $hoy->copy();
        $ventasFacturadas = Venta::where('estado', 'registrada')
            ->where('tipo_documento', 'factura')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->get();

        $porNombre = collect(['San Benjamin', 'Supra Medic'])
            ->map(function ($nombreComercial) use ($ventasFacturadas) {
                $items = $ventasFacturadas->where('nombre_comercial', $nombreComercial);

                return [
                    'nombre_comercial' => $nombreComercial,
                    'cantidad' => $items->count(),
                    'total' => $items->sum('total'),
                ];
            });

        $otros = $ventasFacturadas
            ->reject(fn ($venta) => in_array($venta->nombre_comercial, ['San Benjamin', 'Supra Medic'], true))
            ->groupBy(fn ($venta) => $venta->nombre_comercial ?: 'Sin nombre comercial')
            ->map(fn ($items, $nombreComercial) => [
                'nombre_comercial' => $nombreComercial,
                'cantidad' => $items->count(),
                'total' => $items->sum('total'),
            ])
            ->values();

        return [
            'titulo' => $hoy->isLastOfMonth() ? 'Cierre de facturación mensual' : 'Corte de facturación al día 20',
            'desde' => $desde,
            'hasta' => $hasta,
            'items' => $porNombre->concat($otros)->values(),
            'total' => $ventasFacturadas->sum('total'),
            'ruta' => route('reportes.documentos', [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'tipo_documento' => 'factura',
            ]),
        ];
    }

    private function ultimasOperaciones(Collection $ventas, Collection $compras): Collection
    {
        $ventas = $ventas->map(fn ($venta) => [
                'tipo' => $venta->tipo_documento === 'nota_entrega' ? 'Nota de entrega' : 'Venta',
            'numero' => $venta->numero_venta,
            'fecha' => $venta->fecha,
            'orden_fecha' => $venta->created_at ?? $venta->fecha,
            'nombre_comercial' => $venta->nombre_comercial,
            'tercero' => $venta->cliente?->nombre_mostrable ?? 'Sin cliente',
            'total' => (float) $venta->total,
        ]);

        $compras = $compras->map(fn ($compra) => [
            'tipo' => 'Compra',
            'numero' => $compra->numero_compra,
            'fecha' => $compra->fecha,
            'orden_fecha' => $compra->created_at ?? $compra->fecha,
            'nombre_comercial' => $compra->nombre_comercial,
            'tercero' => $compra->proveedor?->nombre ?? 'Sin proveedor',
            'total' => (float) $compra->total,
        ]);

        return $ventas
            ->concat($compras)
            ->sortByDesc('orden_fecha')
            ->take(5)
            ->values();
    }

    private function entregasSinStockPendientes(Collection $productosStockNegativo): Collection
    {
        if ($productosStockNegativo->isEmpty()) {
            return collect();
        }

        return MovimientoInventario::with(['producto', 'venta'])
            ->where('referencia_tipo', 'venta')
            ->whereIn('producto_id', $productosStockNegativo->pluck('id'))
            ->whereHas('venta', fn ($query) => $query
                ->where('permitir_salida_sin_stock', true)
                ->where('estado', 'registrada'))
            ->latest('fecha_movimiento')
            ->latest('id')
            ->get()
            ->filter(fn (MovimientoInventario $movimiento) => $movimiento->producto && $movimiento->producto->stock_actual < 0)
            ->unique('producto_id')
            ->values();
    }

    private function alertasDatos(Collection $productosSinPrecio, Collection $productosSinCategoria, Collection $productosStockNegativo, Collection $entregasSinStockPendientes, Collection $productosSinMarcaProcedencia, Collection $lotesSinVencimiento): Collection
    {
        return collect([
            [
                'titulo' => 'Productos sin precio',
                'total' => $productosSinPrecio->count(),
                'detalle' => $productosSinPrecio->take(2)->pluck('nombre')->join(', ') ?: 'Sin pendientes',
                'ruta' => route('productos.index'),
                'permiso' => 'productos',
            ],
            [
                'titulo' => 'Productos sin categoría',
                'total' => $productosSinCategoria->count(),
                'detalle' => $productosSinCategoria->take(2)->pluck('nombre')->join(', ') ?: 'Sin pendientes',
                'ruta' => route('productos.index'),
                'permiso' => 'productos',
            ],
            [
                'titulo' => 'Stock negativo',
                'total' => $productosStockNegativo->count(),
                'detalle' => $productosStockNegativo->take(2)->pluck('nombre')->join(', ') ?: 'Sin pendientes',
                'ruta' => route('inventario.index'),
                'permiso' => 'inventario',
            ],
            [
                'titulo' => 'Faltantes de stock por regularizar',
                'total' => $productosStockNegativo->count(),
                'detalle' => $productosStockNegativo->take(2)->map(fn ($producto) => $producto->codigo.' - '.$producto->nombre.' ('.number_format(abs((float) $producto->stock_actual), 2).' faltante)')->join(', ') ?: 'Sin pendientes',
                'ruta' => route('productos.index'),
                'permiso' => 'productos',
            ],
            [
                'titulo' => 'Documentos con stock pendiente',
                'total' => $entregasSinStockPendientes->count(),
                'detalle' => $entregasSinStockPendientes->take(2)->map(fn ($movimiento) => $movimiento->producto?->nombre . ' (' . number_format(abs((float) $movimiento->producto?->stock_actual), 2) . ' faltante)')->join(', ') ?: 'Sin pendientes',
                'ruta' => route('inventario.index', ['stock_hasta' => 0]),
                'permiso' => 'inventario',
            ],
            [
                'titulo' => 'Productos sin marca o procedencia',
                'total' => $productosSinMarcaProcedencia->count(),
                'detalle' => $productosSinMarcaProcedencia->take(2)->pluck('nombre')->join(', ') ?: 'Sin pendientes',
                'ruta' => route('productos.index'),
                'permiso' => 'productos',
            ],
            [
                'titulo' => 'Lotes con stock sin vencimiento',
                'total' => $lotesSinVencimiento->count(),
                'detalle' => $lotesSinVencimiento->take(2)->map(fn ($lote) => $lote->producto?->nombre)->filter()->join(', ') ?: 'Sin pendientes',
                'ruta' => route('inventario.lotes.index', ['vencimiento' => 'sin_fecha']),
                'permiso' => 'inventario',
            ],
        ]);
    }
}
