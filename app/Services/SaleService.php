<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\MovimientoInventario;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class SaleService
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    public function registrar(array $data, int $usuarioId): Venta
    {
        $items = collect($data['items'] ?? [])->filter(fn ($item) => !empty($item['producto_id']));

        if ($items->isEmpty()) {
            throw new InvalidArgumentException('La venta debe tener al menos un producto.');
        }

        return DB::transaction(function () use ($data, $items, $usuarioId) {
            $esNotaEntrega = ($data['tipo_documento'] ?? 'factura') === 'nota_entrega';
            $esFacturaStockPendiente = ! $esNotaEntrega
                && ($data['tipo_documento'] ?? 'factura') === 'factura'
                && (bool) ($data['factura_stock_pendiente'] ?? false);
            // Las notas de entrega pueden documentar una salida pendiente aunque
            // no exista stock disponible. Las facturas requieren confirmación explícita.
            $permitirSalidaSinStock = $esNotaEntrega
                || ($esFacturaStockPendiente && (bool) ($data['permitir_salida_sin_stock'] ?? false));
            $motivoSalidaSinStock = trim((string) ($data['motivo_salida_sin_stock'] ?? ''));
            if ($esFacturaStockPendiente && ! $permitirSalidaSinStock) {
                throw new InvalidArgumentException('Confirme que la factura se registrará con stock pendiente.');
            }
            if ($permitirSalidaSinStock && $motivoSalidaSinStock === '' && ! $esNotaEntrega) {
                throw new InvalidArgumentException('Indique el motivo del stock pendiente.');
            }
            if ($esNotaEntrega && $motivoSalidaSinStock === '') {
                $motivoSalidaSinStock = 'Nota de entrega registrada sin existencia disponible.';
            }
            if (($data['plan_pago'] ?? 'contado') === 'cuotas' && ! Schema::hasTable('venta_cuotas')) {
                throw new InvalidArgumentException('La actualización de la base de datos está pendiente. No se puede registrar una venta en cuotas todavía.');
            }
            $subtotal = 0;
            $descuentoTotal = 0;
            $impuestoTotal = 0;
            $costoEnvioTotal = 0;
            $otrosCostosTotal = 0;
            $total = 0;
            $descuentoPorcentaje = (float) ($data['descuento_porcentaje'] ?? 0);
            $productos = Producto::whereIn('id', $items->pluck('producto_id'))->lockForUpdate()->get()->keyBy('id');

            foreach ($items as $item) {
                $producto = $productos->get((int) $item['producto_id']);
                $cantidad = (float) $item['cantidad'];
                $precio = (float) $item['precio_unitario'];
                $lineaSubtotal = $cantidad * $precio;
                $descuento = $this->lineAmount($item, 'descuento', round($lineaSubtotal * ($descuentoPorcentaje / 100), 2));
                $impuesto = $this->lineAmount($item, 'impuesto');
                $costoEnvio = $this->lineAmount($item, 'costo_envio');
                $otrosCostos = $this->lineAmount($item, 'otros_costos');
                $lineaTotal = max(0, $lineaSubtotal - $descuento) + $impuesto + $costoEnvio + $otrosCostos;

                if (! $producto || $cantidad <= 0 || $precio < 0 || (! $esNotaEntrega && $precio <= 0) || $descuento < 0 || $impuesto < 0 || $costoEnvio < 0 || $otrosCostos < 0) {
                    throw new InvalidArgumentException('Detalle de venta inválido: revise cantidad, precio y montos.');
                }

                if ($this->inventory->stockDisponibleParaVenta($producto) < $cantidad && ! $permitirSalidaSinStock) {
                    throw new InvalidArgumentException("Stock vigente insuficiente para {$producto->codigo} - {$producto->nombre}.");
                }

                $subtotal += $lineaSubtotal;
                $descuentoTotal += $descuento;
                $impuestoTotal += $impuesto;
                $costoEnvioTotal += $costoEnvio;
                $otrosCostosTotal += $otrosCostos;
                $total += $lineaTotal;
            }

            $venta = Venta::create([
                'numero_venta' => $data['numero_venta'] ?: $this->siguienteNumero(),
                'nombre_comercial' => $data['nombre_comercial'] ?? null,
                'tipo_documento' => $data['tipo_documento'] ?? 'factura',
                'cliente_id' => $data['cliente_id'] ?? null,
                'fecha' => $data['fecha'],
                'factura' => $data['factura'] ?? null,
                'nit_cliente' => $data['nit_cliente'] ?? null,
                'lista_precios' => $data['lista_precios'] ?? 'Precio de Venta',
                'subtotal' => $subtotal,
                'descuento_porcentaje' => $descuentoPorcentaje,
                'descuento' => $descuentoTotal,
                'impuesto_total' => $impuestoTotal,
                'costo_envio_total' => $costoEnvioTotal,
                'otros_costos_total' => $otrosCostosTotal,
                'total' => max(0, $total),
                'estado' => 'registrada',
                'observacion' => $data['observacion'] ?? null,
                'permitir_salida_sin_stock' => $permitirSalidaSinStock,
                'motivo_salida_sin_stock' => $permitirSalidaSinStock ? $motivoSalidaSinStock : null,
                'usuario_id' => $usuarioId,
            ]);

            if (($data['plan_pago'] ?? 'contado') === 'cuotas') {
                $this->registrarCuotas($venta, $data['cuotas'] ?? []);
            }

            foreach ($items as $item) {
                $producto = $productos->get((int) $item['producto_id']);
                $cantidad = (float) $item['cantidad'];
                $precio = (float) $item['precio_unitario'];
                $lineaSubtotal = $cantidad * $precio;
                $descuento = $this->lineAmount($item, 'descuento', round($lineaSubtotal * ($descuentoPorcentaje / 100), 2));
                $impuesto = $this->lineAmount($item, 'impuesto');
                $costoEnvio = $this->lineAmount($item, 'costo_envio');
                $otrosCostos = $this->lineAmount($item, 'otros_costos');

                $detalle = $venta->detalles()->create([
                    'producto_id' => $producto->id,
                    'codigo_producto' => $producto->codigo,
                    'nombre_producto' => $producto->nombre,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'subtotal' => $lineaSubtotal,
                    'descuento' => $descuento,
                    'impuesto' => $impuesto,
                    'costo_envio' => $costoEnvio,
                    'otros_costos' => $otrosCostos,
                    'total' => max(0, $lineaSubtotal - $descuento) + $impuesto + $costoEnvio + $otrosCostos,
                ]);

                $pendienteLotes = $this->inventory->consumirLotesPorVencimiento(
                    $producto,
                    $detalle,
                    $cantidad,
                    ! empty($item['inventario_lote_id']) ? (int) $item['inventario_lote_id'] : null,
                    $item['fecha_vencimiento'] ?? null,
                    $permitirSalidaSinStock
                );
                if ($pendienteLotes > 0 && ! $permitirSalidaSinStock) {
                    throw new InvalidArgumentException("No hay lotes vigentes suficientes para {$producto->codigo} - {$producto->nombre}.");
                }
                $this->inventory->registrarMovimiento(
                    $producto,
                    'salida',
                    $cantidad,
                    'venta',
                    $venta->id,
                    $usuarioId,
                    $permitirSalidaSinStock
                        ? ($esFacturaStockPendiente ? 'Salida por factura con stock pendiente: ' : 'Salida por nota de entrega sin stock: ') . $motivoSalidaSinStock
                        : 'Salida por venta',
                    null,
                    $permitirSalidaSinStock
                );
            }

            return $venta->load('detalles', 'cliente', 'usuario', 'cuotas');
        });
    }

    public function anular(Venta $venta, int $usuarioId): Venta
    {
        if ($venta->estado === 'anulada') {
            return $venta;
        }

        return DB::transaction(function () use ($venta, $usuarioId) {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            if ($venta->estado === 'anulada') {
                return $venta;
            }
            $venta->load('detalles.producto');
            if ($venta->cobros()->where('estado', 'registrado')->exists()) {
                throw new InvalidArgumentException('La venta tiene cobros registrados. Revise y regularice los cobros antes de anularla.');
            }

            foreach ($venta->detalles as $detalle) {
                $this->inventory->devolverLotesDeVenta($detalle);
                $this->inventory->registrarMovimiento($detalle->producto, 'anulacion', (float) $detalle->cantidad, 'venta', $venta->id, $usuarioId, 'Anulacion de venta');
            }

            $venta->update(['estado' => 'anulada']);

            return $venta;
        });
    }

    /**
     * Removes a document entered in error and leaves the inventory ledger as if
     * the document had never been registered. Payments deliberately prevent
     * deletion because they are accounting records that must not be orphaned.
     *
     * @return array<string, mixed>
     */
    public function eliminarDefinitivamente(Venta $venta): array
    {
        return DB::transaction(function () use ($venta) {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            $venta->load('detalles.producto', 'detalles.lotes.lote');

            if ($venta->cobros()->exists()) {
                throw new InvalidArgumentException('El documento tiene cobros registrados o anulados. Regularice los cobros antes de eliminarlo definitivamente.');
            }

            $before = $venta->only([
                'id', 'numero_venta', 'tipo_documento', 'nombre_comercial', 'fecha', 'factura', 'total', 'estado',
            ]);
            $productosAfectados = $venta->detalles->pluck('producto_id')->unique()->filter()->values();

            // An annulled document already returned its lots. A registered one has not.
            if ($venta->estado !== 'anulada') {
                foreach ($venta->detalles as $detalle) {
                    $this->inventory->devolverLotesDeVenta($detalle);
                }
            }

            MovimientoInventario::where('referencia_tipo', 'venta')
                ->where('referencia_id', $venta->id)
                ->delete();

            $venta->delete();

            foreach ($productosAfectados as $productoId) {
                $this->recalcularMovimientosProducto((int) $productoId);
            }

            return $before;
        });
    }

    public function siguienteNumero(): string
    {
        $next = Venta::query()
            ->pluck('numero_venta')
            ->map(fn (string $numero) => preg_match('/^(?:EXCEL-)?V-(\d+)(?:-\d+)?$/', $numero, $matches) ? (int) $matches[1] : 0)
            ->max() + 1;

        return 'V-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function lineAmount(array $item, string $key, float $default = 0): float
    {
        return max(0, (float) ($item[$key] ?? $default));
    }

    /**
     * Movement rows store running balances. After a historical document is
     * removed, every later balance for that product must be rebuilt as well.
     */
    private function recalcularMovimientosProducto(int $productoId): void
    {
        $producto = Producto::whereKey($productoId)->lockForUpdate()->first();
        if (! $producto) {
            return;
        }

        $stock = 0.0;
        $valor = 0.0;
        $valorConocido = true;
        $movimientos = MovimientoInventario::where('producto_id', $productoId)
            ->orderBy('fecha_movimiento')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($movimientos as $movimiento) {
            $cantidad = (float) $movimiento->cantidad;
            $entrada = in_array($movimiento->tipo_movimiento, ['entrada', 'ajuste_positivo', 'devolucion', 'anulacion'], true);
            $stockAnterior = $stock;
            $valorAnterior = $valorConocido ? $valor : null;
            $costo = $movimiento->costo_valoracion === null ? null : (float) $movimiento->costo_valoracion;

            if ($costo === null && $valorConocido && $stockAnterior > 0) {
                $costo = $valor / $stockAnterior;
            }

            $stock = round($stockAnterior + ($entrada ? $cantidad : -$cantidad), 2);
            if ($valorConocido && $costo !== null) {
                $valor = round($valor + ($entrada ? 1 : -1) * $cantidad * $costo, 6);
            } elseif ($stock !== 0) {
                $valorConocido = false;
            }

            if ($stock == 0.0) {
                $valor = 0.0;
                $valorConocido = true;
            }

            $movimiento->forceFill([
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stock,
                'costo_valoracion' => $costo,
                'valor_anterior' => $valorAnterior,
                'valor_nuevo' => $valorConocido ? $valor : null,
            ])->save();
        }

        $producto->forceFill([
            'valor_inventario' => $valorConocido ? $valor : null,
            'costo_promedio' => $valorConocido && $stock > 0 ? round($valor / $stock, 6) : null,
        ])->save();
    }

    private function registrarCuotas(Venta $venta, array $cuotas): void
    {
        if (count($cuotas) < 2) {
            throw new InvalidArgumentException('Para pago en cuotas registre por lo menos dos vencimientos.');
        }

        $totalCentavos = (int) round((float) $venta->total * 100);
        $cuotasCentavos = 0;
        $fechaAnterior = null;
        foreach ($cuotas as $index => $cuota) {
            $fecha = $cuota['fecha_vencimiento'] ?? null;
            $importeCentavos = (int) round((float) ($cuota['importe'] ?? 0) * 100);
            if (! $fecha || $importeCentavos <= 0 || ($fechaAnterior && $fecha < $fechaAnterior)) {
                throw new InvalidArgumentException('Revise las fechas e importes del calendario de cuotas.');
            }
            $fechaAnterior = $fecha;
            $cuotasCentavos += $importeCentavos;
        }
        if ($cuotasCentavos !== $totalCentavos) {
            throw new InvalidArgumentException('La suma de las cuotas debe ser igual al total de la venta.');
        }

        foreach ($cuotas as $index => $cuota) {
            $venta->cuotas()->create([
                'numero' => $index + 1,
                'fecha_vencimiento' => $cuota['fecha_vencimiento'],
                'importe' => $cuota['importe'],
                'observacion' => $cuota['observacion'] ?? null,
            ]);
        }
    }
}
