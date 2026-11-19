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
            $modoStockPendiente = array_key_exists('modo_stock', $data)
                ? ($data['modo_stock'] ?? null) === 'pendiente'
                : ($esNotaEntrega || (bool) ($data['factura_stock_pendiente'] ?? false));
            $esFacturaStockPendiente = ! $esNotaEntrega
                && ($data['tipo_documento'] ?? 'factura') === 'factura'
                && (
                    $modoStockPendiente
                    || (bool) ($data['permitir_salida_sin_stock'] ?? false)
                );
            // Las notas de entrega pueden documentar una salida pendiente aunque
            // no exista stock disponible. Las facturas requieren confirmación explícita.
            $permitirSalidaSinStock = ($esNotaEntrega && ($modoStockPendiente || (bool) ($data['permitir_salida_sin_stock'] ?? false)))
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
            // C/T is a per-product decision. The line flags are authoritative;
            // the header selector only provides a convenient default in the form.
            $hayTarjeta = $items->contains(fn ($item) => (bool) ($item['tarjeta_magnetica'] ?? false));
            $productos = Producto::whereIn('id', $items->pluck('producto_id'))
                ->where('activo', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $producto = $productos->get((int) $item['producto_id']);
                $cantidad = (float) $item['cantidad'];
                $precio = (float) $item['precio_unitario'];
                $esLoteManual = filled($item['codigo_lote_manual'] ?? null);
                $lineaSubtotal = $cantidad * $precio;
                $descuento = $this->lineAmount($item, 'descuento', round($lineaSubtotal * ($descuentoPorcentaje / 100), 2));
                $impuesto = $this->lineAmount($item, 'impuesto');
                $costoEnvio = $this->lineAmount($item, 'costo_envio');
                $otrosCostos = $this->lineAmount($item, 'otros_costos');
                $lineaTotal = max(0, $lineaSubtotal - $descuento) + $impuesto + $costoEnvio + $otrosCostos;

                if (! $producto || $cantidad <= 0 || $precio < 0 || $descuento < 0 || $impuesto < 0 || $costoEnvio < 0 || $otrosCostos < 0) {
                    throw new InvalidArgumentException('Detalle de venta inválido: revise cantidad, precio y montos.');
                }
                if ($descuento > round($lineaSubtotal, 2) + 0.0001) {
                    throw new InvalidArgumentException("El descuento no puede superar el subtotal de {$producto->codigo} - {$producto->nombre}.");
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
                'tarjeta_magnetica' => $hayTarjeta,
                'tipo_tarjeta' => $hayTarjeta ? ($data['tipo_tarjeta'] ?? null) : null,
                'destinatario_tarjeta' => $hayTarjeta ? ($data['destinatario_tarjeta'] ?? null) : null,
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
                $esLoteManual = filled($item['codigo_lote_manual'] ?? null);
                $stockDisponibleAntes = $this->inventory->stockDisponibleParaVenta($producto);
                $faltanteStock = $permitirSalidaSinStock
                    ? max(0, round($cantidad - $stockDisponibleAntes, 2))
                    : 0;

                $detalle = $venta->detalles()->create([
                    'producto_id' => $producto->id,
                    'codigo_producto' => $producto->codigo,
                    'nombre_producto' => $producto->nombre,
                    'tarjeta_magnetica' => (bool) ($item['tarjeta_magnetica'] ?? false),
                    'cantidad' => $cantidad,
                    'faltante_stock' => $faltanteStock,
                    'codigo_lote_manual' => trim((string) ($item['codigo_lote_manual'] ?? '')) ?: null,
                    'fecha_vencimiento_manual' => $item['fecha_vencimiento_manual'] ?? ($item['fecha_vencimiento'] ?? null),
                    'precio_unitario' => $precio,
                    'subtotal' => $lineaSubtotal,
                    'descuento' => $descuento,
                    'impuesto' => $impuesto,
                    'costo_envio' => $costoEnvio,
                    'otros_costos' => $otrosCostos,
                    'total' => max(0, $lineaSubtotal - $descuento) + $impuesto + $costoEnvio + $otrosCostos,
                ]);

                $loteSeleccionado = ! empty($item['codigo_lote_manual'])
                    ? null
                    : (! empty($item['inventario_lote_id']) ? (int) $item['inventario_lote_id'] : null);
                $pendienteLotes = $this->inventory->consumirLotesPorVencimiento(
                    $producto,
                    $detalle,
                    $cantidad,
                    $loteSeleccionado,
                    $item['fecha_vencimiento'] ?? null,
                    $permitirSalidaSinStock || $esLoteManual,
                    $esLoteManual
                );
                if ($pendienteLotes > 0 && ! $permitirSalidaSinStock && ! $esLoteManual) {
                    throw new InvalidArgumentException("No hay lotes vigentes suficientes para {$producto->codigo} - {$producto->nombre}.");
                }
                if ($esLoteManual) {
                    $this->inventory->registrarLoteManualDeVenta(
                        $producto,
                        $cantidad,
                        $item['fecha_vencimiento_manual'] ?? ($item['fecha_vencimiento'] ?? null),
                        (string) $item['codigo_lote_manual'],
                        $usuarioId,
                        $permitirSalidaSinStock
                    );
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

    public function actualizarProductos(Venta $venta, array $items, int $usuarioId): Venta
    {
        $items = collect($items)->filter(fn ($item) => ! empty($item['producto_id']))->values();
        if ($items->isEmpty()) {
            throw new InvalidArgumentException('La venta debe conservar al menos un producto.');
        }

        return DB::transaction(function () use ($venta, $items, $usuarioId): Venta {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            if ($venta->estado !== 'registrada') {
                throw new InvalidArgumentException('Solo se pueden editar documentos registrados.');
            }
            if ($venta->cobros()->where('estado', 'registrado')->exists()) {
                throw new InvalidArgumentException('No se puede editar una venta con cobros registrados. Regularice los cobros primero.');
            }
            if ($venta->cuotas()->exists()) {
                throw new InvalidArgumentException('No se puede editar una venta que ya tiene cuotas.');
            }

            $venta->load('detalles.producto', 'detalles.lotes.lote');
            $cantidadesAnteriores = $venta->detalles
                ->groupBy('producto_id')
                ->map(fn ($detalles) => (float) $detalles->sum('cantidad'));
            $cantidadesNuevas = $items->groupBy('producto_id')->map(fn ($detalles) => (float) $detalles->sum('cantidad'));
            $productos = Producto::whereIn('id', $items->pluck('producto_id'))
                ->where('activo', true)->lockForUpdate()->get()->keyBy('id');
            $subtotal = $descuentoTotal = $impuestoTotal = $envioTotal = $otrosTotal = $total = 0.0;
            $descuentoPorcentaje = (float) ($venta->descuento_porcentaje ?? 0);

            foreach ($items as $item) {
                $producto = $productos->get((int) $item['producto_id']);
                $cantidad = (float) ($item['cantidad'] ?? 0);
                $precio = (float) ($item['precio_unitario'] ?? 0);
                $lineaSubtotal = round($cantidad * $precio, 2);
                $descuento = $this->lineAmount($item, 'descuento', round($lineaSubtotal * ($descuentoPorcentaje / 100), 2));
                $impuesto = $this->lineAmount($item, 'impuesto');
                $envio = $this->lineAmount($item, 'costo_envio');
                $otros = $this->lineAmount($item, 'otros_costos');
                if (! $producto || $cantidad <= 0 || $precio < 0 || $descuento < 0 || $impuesto < 0 || $envio < 0 || $otros < 0 || $descuento > $lineaSubtotal + 0.0001) {
                    throw new InvalidArgumentException('Detalle de venta inválido: revise producto, cantidad, precio y montos.');
                }
                $stockDisponibleConDevolucion = $this->inventory->stockDisponibleParaVenta($producto) + (float) $cantidadesAnteriores->get($producto->id, 0);
                if ($stockDisponibleConDevolucion < (float) $cantidadesNuevas->get($producto->id, $cantidad) && ! $venta->permitir_salida_sin_stock) {
                    throw new InvalidArgumentException("Stock vigente insuficiente para {$producto->codigo} - {$producto->nombre}.");
                }
                $subtotal += $lineaSubtotal;
                $descuentoTotal += $descuento;
                $impuestoTotal += $impuesto;
                $envioTotal += $envio;
                $otrosTotal += $otros;
                $total += max(0, $lineaSubtotal - $descuento) + $impuesto + $envio + $otros;
            }

            $productosAnteriores = $venta->detalles->pluck('producto_id')->unique()->filter()->values();
            foreach ($venta->detalles as $detalle) {
                $this->inventory->devolverLotesDeVenta($detalle);
            }
            MovimientoInventario::where('referencia_tipo', 'venta')->where('referencia_id', $venta->id)->delete();
            $venta->detalles()->delete();
            foreach ($productosAnteriores as $productoId) {
                $this->recalcularMovimientosProducto((int) $productoId);
            }

            $hayTarjeta = $items->contains(fn ($item) => (bool) ($item['tarjeta_magnetica'] ?? false));
            foreach ($items as $item) {
                $producto = $productos->get((int) $item['producto_id']);
                $cantidad = (float) $item['cantidad'];
                $precio = (float) $item['precio_unitario'];
                $lineaSubtotal = round($cantidad * $precio, 2);
                $descuento = $this->lineAmount($item, 'descuento', round($lineaSubtotal * ($descuentoPorcentaje / 100), 2));
                $impuesto = $this->lineAmount($item, 'impuesto');
                $envio = $this->lineAmount($item, 'costo_envio');
                $otros = $this->lineAmount($item, 'otros_costos');
                $manual = trim((string) ($item['codigo_lote_manual'] ?? '')) ?: null;
                $stockAntes = $this->inventory->stockDisponibleParaVenta($producto);
                $detalle = $venta->detalles()->create([
                    'producto_id' => $producto->id,
                    'codigo_producto' => $producto->codigo,
                    'nombre_producto' => $producto->nombre,
                    'tarjeta_magnetica' => (bool) ($item['tarjeta_magnetica'] ?? false),
                    'cantidad' => $cantidad,
                    'faltante_stock' => $venta->permitir_salida_sin_stock ? max(0, round($cantidad - $stockAntes, 2)) : 0,
                    'codigo_lote_manual' => $manual,
                    'fecha_vencimiento_manual' => $item['fecha_vencimiento_manual'] ?? null,
                    'precio_unitario' => $precio,
                    'subtotal' => $lineaSubtotal,
                    'descuento' => $descuento,
                    'impuesto' => $impuesto,
                    'costo_envio' => $envio,
                    'otros_costos' => $otros,
                    'total' => max(0, $lineaSubtotal - $descuento) + $impuesto + $envio + $otros,
                ]);
                $pendiente = $this->inventory->consumirLotesPorVencimiento(
                    $producto, $detalle, $cantidad,
                    $manual ? null : (($item['inventario_lote_id'] ?? null) ?: null),
                    $item['fecha_vencimiento'] ?? null,
                    (bool) $venta->permitir_salida_sin_stock || (bool) $manual,
                    (bool) $manual
                );
                if ($pendiente > 0 && ! $venta->permitir_salida_sin_stock && ! $manual) {
                    throw new InvalidArgumentException("No hay lotes vigentes suficientes para {$producto->codigo} - {$producto->nombre}.");
                }
                if ($manual) {
                    $this->inventory->registrarLoteManualDeVenta($producto, $cantidad, $item['fecha_vencimiento_manual'] ?? null, $manual, $usuarioId, (bool) $venta->permitir_salida_sin_stock);
                }
                $this->inventory->registrarMovimiento($producto, 'salida', $cantidad, 'venta', $venta->id, $usuarioId, $venta->permitir_salida_sin_stock ? 'Salida por edición con stock pendiente' : 'Salida por edición de venta', null, (bool) $venta->permitir_salida_sin_stock);
            }

            return $venta->update([
                'subtotal' => $subtotal,
                'descuento' => $descuentoTotal,
                'impuesto_total' => $impuestoTotal,
                'costo_envio_total' => $envioTotal,
                'otros_costos_total' => $otrosTotal,
                'total' => max(0, $total),
                'tarjeta_magnetica' => $hayTarjeta,
                'tipo_tarjeta' => $hayTarjeta ? $venta->tipo_tarjeta : null,
                'destinatario_tarjeta' => $hayTarjeta ? $venta->destinatario_tarjeta : null,
            ]) ? $venta->fresh('detalles') : $venta;
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
     * Active payments prevent deletion; cancelled payments are historical
     * correction records and are removed with the document after TOTP approval.
     *
     * @return array<string, mixed>
     */
    public function eliminarDefinitivamente(Venta $venta): array
    {
        return DB::transaction(function () use ($venta) {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            $venta->load('detalles.producto', 'detalles.lotes.lote');

            if ($venta->cobros()->where('estado', 'registrado')->exists()) {
                throw new InvalidArgumentException('El documento todavía tiene cobros registrados. Anule o regularice los cobros antes de eliminarlo definitivamente.');
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

            // The foreign key on cobros restricts deleting the sale. At this
            // point only cancelled historical cobros may remain.
            $venta->cobros()->delete();
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
