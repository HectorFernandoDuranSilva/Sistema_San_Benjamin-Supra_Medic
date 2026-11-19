<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\InventarioLote;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseService
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    public function registrar(array $data, int $usuarioId): Compra
    {
        $items = collect($data['items'] ?? [])->filter(fn ($item) => !empty($item['producto_id']));
        if ($items->isEmpty()) {
            throw new InvalidArgumentException('La compra debe tener al menos un producto. Registre combustible, envío, transporte o recojo desde Notas y facturas.');
        }
        return DB::transaction(function () use ($data, $items, $usuarioId) {
            $subtotal = 0;
            $descuentoTotal = 0;
            $impuestoTotal = 0;
            $productos = Producto::whereIn('id', $items->pluck('producto_id'))
                ->where('activo', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $producto = $productos->get((int) $item['producto_id']);
                $cantidad = (float) $item['cantidad'];
                $costo = (float) $item['costo_unitario'];
                $porcentajeUtilidad = (float) ($item['porcentaje_utilidad'] ?? 27);
                $descuentoFijo = (float) ($item['descuento_fijo'] ?? 0);
                $impuesto = (float) ($item['impuesto'] ?? 0);

                $subtotalDetalle = round($cantidad * $costo, 2);
                if (! $producto || $cantidad < 0 || $costo < 0 || $impuesto < 0 || $porcentajeUtilidad < 25 || $porcentajeUtilidad > 30 || $descuentoFijo < 0 || $descuentoFijo > $subtotalDetalle) {
                    throw new InvalidArgumentException('Detalle de compra inválido: revise cantidad, costo, descuento, impuesto y utilidad.');
                }

                $subtotal += $subtotalDetalle;
                $descuentoTotal += round(min($subtotalDetalle, $descuentoFijo), 2);
                $impuestoTotal += $impuesto;
            }

            $compra = Compra::create([
                'numero_compra' => $data['numero_compra'] ?: $this->siguienteNumero(),
                'nombre_comercial' => $data['nombre_comercial'] ?? null,
                'tipo_documento' => $data['tipo_documento'] ?? 'factura',
                'proveedor_id' => $data['proveedor_id'] ?? null,
                'fecha' => $data['fecha'],
                'factura' => $data['factura'] ?? null,
                'nit_proveedor' => $data['nit_proveedor'] ?? null,
                'subtotal' => $subtotal,
                'descuento_total' => $descuentoTotal,
                'impuesto_total' => $impuestoTotal,
                'total' => $subtotal - $descuentoTotal + $impuestoTotal,
                'estado' => 'registrada',
                'observacion' => $data['observacion'] ?? null,
                'usuario_id' => $usuarioId,
            ]);

            if (($data['plan_pago'] ?? 'contado') === 'cuotas') {
                $this->registrarCuotas($compra, $data['cuotas'] ?? []);
            }

            foreach ($items as $item) {
                $producto = $productos->get((int) $item['producto_id']);
                $cantidad = (float) $item['cantidad'];
                $costo = (float) $item['costo_unitario'];
                $porcentajeUtilidad = (float) ($item['porcentaje_utilidad'] ?? 27);
                $descuentoFijo = (float) ($item['descuento_fijo'] ?? 0);
                $impuesto = (float) ($item['impuesto'] ?? 0);
                $tipoLote = $item['tipo_lote'] ?? 'con_lote';
                $codigoLote = $producto->maneja_lote_universal
                    ? 'UNIVERSAL'
                    : (trim((string) ($item['codigo_lote'] ?? '')) ?: null);
                if ($tipoLote === 'sin_lote') {
                    $codigoLote = null;
                }
                $fechaVencimiento = $tipoLote === 'sin_vencimiento' || $tipoLote === 'sin_lote'
                    ? null
                    : ($item['fecha_vencimiento'] ?? null);
                $subtotalDetalle = round($cantidad * $costo, 2);
                $descuentoDetalle = round(min($subtotalDetalle, $descuentoFijo), 2);
                $costoNetoUnitario = $cantidad > 0
                    ? round(($subtotalDetalle - $descuentoDetalle) / $cantidad, 4)
                    : 0;

                $detalle = $compra->detalles()->create([
                    'producto_id' => $producto->id,
                    'codigo_producto' => $producto->codigo,
                    'nombre_producto' => $producto->nombre,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costo,
                    'costo_neto_unitario' => $costoNetoUnitario,
                    'porcentaje_utilidad' => $porcentajeUtilidad,
                    'descuento_porcentaje' => 0,
                    'descuento_fijo' => $descuentoFijo,
                    'codigo_lote' => $codigoLote,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'subtotal' => $subtotalDetalle,
                    'descuento' => $descuentoDetalle,
                    'impuesto' => $impuesto,
                    'total' => $subtotalDetalle - $descuentoDetalle + $impuesto,
                ]);

                $actualizacionProducto = ['porcentaje_utilidad' => $porcentajeUtilidad];

                // A free item is still a real stock entry, but it must not
                // erase the product's useful cost basis or sale price.
                if ($costoNetoUnitario > 0) {
                    $actualizacionProducto['ultimo_costo_compra'] = $costoNetoUnitario;
                }

                // A new product can be created before its first purchase, so
                // its cost fields may still be zero. The purchase is the first
                // reliable Bs cost in that case. Seed the missing product
                // pricing only; never overwrite an already configured price.
                if ($costoNetoUnitario > 0 && (float) $producto->costo_referencial <= 0) {
                    $actualizacionProducto['moneda_costo'] = 'bs';
                    $actualizacionProducto['precio_ingreso_usd'] = 0;
                    $actualizacionProducto['costo_referencial'] = $costoNetoUnitario;
                }

                if ($costoNetoUnitario > 0 && (float) $producto->precio_venta <= 0) {
                    $actualizacionProducto['precio_venta'] = round(
                        $costoNetoUnitario * (1 + ($porcentajeUtilidad / 100)),
                        2
                    );
                }

                $producto->forceFill($actualizacionProducto)->save();

                // A zero quantity documents a pending line in the purchase,
                // but it must not create stock, a lot, or a Kardex movement.
                if ($cantidad <= 0) {
                    continue;
                }

                $this->inventory->registrarMovimiento($producto, 'entrada', $cantidad, 'compra', $compra->id, $usuarioId, 'Entrada por compra', $costoNetoUnitario);
                $this->inventory->aplicarEntradaAFaltantes($producto, $cantidad);
                if ($tipoLote !== 'sin_lote') {
                    $this->inventory->registrarLoteEntrada(
                        $producto,
                        $cantidad,
                        $compra->id,
                        $detalle->id,
                        $costoNetoUnitario,
                        $fechaVencimiento,
                        $codigoLote,
                        $usuarioId,
                        $tipoLote === 'sin_vencimiento'
                            ? 'Lote creado por compra sin fecha de vencimiento'
                            : 'Lote creado por compra'
                    );
                }
            }

            return $compra->load('detalles', 'gastos', 'proveedor', 'usuario', 'cuotas');
        });
    }

    public function siguienteNumero(): string
    {
        $next = Compra::query()
            ->pluck('numero_compra')
            ->map(fn (string $numero) => preg_match('/^(?:EXCEL-)?C-(\d+)(?:-\d+)?$/', $numero, $matches) ? (int) $matches[1] : 0)
            ->max() + 1;

        return 'C-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    public function actualizarProductos(Compra $compra, array $items, int $usuarioId): Compra
    {
        $items = collect($items)->filter(fn ($item) => array_key_exists('producto_id', $item))->values();
        if ($items->isEmpty()) {
            throw new InvalidArgumentException('La compra debe conservar al menos un producto.');
        }

        return DB::transaction(function () use ($compra, $items, $usuarioId): Compra {
            $compra = Compra::whereKey($compra->id)->lockForUpdate()->firstOrFail();
            if ($compra->estado !== 'registrada') {
                throw new InvalidArgumentException('Solo se pueden editar compras registradas.');
            }
            if ($compra->pagos()->where('estado', 'registrado')->exists()) {
                throw new InvalidArgumentException('Regularice los pagos antes de editar los productos.');
            }
            if ($compra->cuotas()->exists()) {
                throw new InvalidArgumentException('No se puede editar una compra que ya tiene cuotas.');
            }

            $compra->load('detalles.lote.consumos');
            if ($compra->detalles->contains(fn ($detalle) => $detalle->lote && $detalle->lote->consumos->isNotEmpty())) {
                throw new InvalidArgumentException('No se puede editar: uno de los lotes de esta compra ya fue utilizado en una venta.');
            }
            if ($compra->detalles->contains(fn ($detalle) => $detalle->producto?->maneja_lote_universal)) {
                throw new InvalidArgumentException('No se puede editar directamente una compra que usa lote universal. Ajuste el ingreso desde Productos.');
            }

            $productos = Producto::whereIn('id', $items->pluck('producto_id'))->where('activo', true)->lockForUpdate()->get()->keyBy('id');
            foreach ($items as $item) {
                $producto = $productos->get((int) $item['producto_id']);
                $cantidad = (float) ($item['cantidad'] ?? 0);
                $costo = (float) ($item['costo_unitario'] ?? 0);
                $utilidad = (float) ($item['porcentaje_utilidad'] ?? 27);
                $descuento = (float) ($item['descuento_fijo'] ?? 0);
                if (! $producto || $cantidad < 0 || $costo < 0 || $utilidad < 25 || $utilidad > 30 || $descuento < 0 || $descuento > round($cantidad * $costo, 2)) {
                    throw new InvalidArgumentException('Detalle de compra inválido: revise producto, cantidad, costo, descuento y utilidad.');
                }
            }

            $productosAnteriores = $compra->detalles->pluck('producto_id')->unique()->filter()->values();
            foreach ($compra->detalles as $detalle) {
                if ($detalle->lote) {
                    $detalle->lote->delete();
                }
            }
            MovimientoInventario::where('referencia_tipo', 'compra')->where('referencia_id', $compra->id)->delete();
            $compra->detalles()->delete();
            foreach ($productosAnteriores as $productoId) {
                $this->recalcularMovimientosProducto((int) $productoId);
            }

            $subtotal = $descuentoTotal = $impuestoTotal = 0.0;
            foreach ($items as $item) {
                $producto = $productos->get((int) $item['producto_id']);
                $cantidad = (float) $item['cantidad'];
                $costo = (float) $item['costo_unitario'];
                $utilidad = (float) ($item['porcentaje_utilidad'] ?? 27);
                $descuentoFijo = (float) ($item['descuento_fijo'] ?? 0);
                $impuesto = (float) ($item['impuesto'] ?? 0);
                $tipoLote = $item['tipo_lote'] ?? 'con_lote';
                $codigoLote = $producto->maneja_lote_universal ? 'UNIVERSAL' : (trim((string) ($item['codigo_lote'] ?? '')) ?: null);
                if ($tipoLote === 'sin_lote') $codigoLote = null;
                $fecha = in_array($tipoLote, ['sin_lote', 'sin_vencimiento'], true) ? null : ($item['fecha_vencimiento'] ?? null);
                $linea = round($cantidad * $costo, 2);
                $descuento = round(min($linea, $descuentoFijo), 2);
                $neto = $cantidad > 0 ? round(($linea - $descuento) / $cantidad, 4) : 0;
                $subtotal += $linea;
                $descuentoTotal += $descuento;
                $impuestoTotal += $impuesto;
                $detalle = $compra->detalles()->create(['producto_id'=>$producto->id,'codigo_producto'=>$producto->codigo,'nombre_producto'=>$producto->nombre,'cantidad'=>$cantidad,'costo_unitario'=>$costo,'costo_neto_unitario'=>$neto,'porcentaje_utilidad'=>$utilidad,'descuento_porcentaje'=>0,'descuento_fijo'=>$descuentoFijo,'codigo_lote'=>$codigoLote,'fecha_vencimiento'=>$fecha,'subtotal'=>$linea,'descuento'=>$descuento,'impuesto'=>$impuesto,'total'=>$linea-$descuento+$impuesto]);
                $actualizacionProducto = ['porcentaje_utilidad' => $utilidad];
                if ($neto > 0) {
                    $actualizacionProducto['ultimo_costo_compra'] = $neto;
                    if ((float) $producto->costo_referencial <= 0) {
                        $actualizacionProducto['moneda_costo'] = 'bs';
                        $actualizacionProducto['precio_ingreso_usd'] = 0;
                        $actualizacionProducto['costo_referencial'] = $neto;
                    }
                    if ((float) $producto->precio_venta <= 0) {
                        $actualizacionProducto['precio_venta'] = round($neto * (1 + ($utilidad / 100)), 2);
                    }
                }
                $producto->forceFill($actualizacionProducto)->save();
                if ($cantidad > 0) {
                    $this->inventory->registrarMovimiento($producto, 'entrada', $cantidad, 'compra', $compra->id, $usuarioId, 'Entrada por edición de compra', $neto);
                    $this->inventory->aplicarEntradaAFaltantes($producto, $cantidad);
                    if ($tipoLote !== 'sin_lote') $this->inventory->registrarLoteEntrada($producto, $cantidad, $compra->id, $detalle->id, $neto, $fecha, $codigoLote, $usuarioId, 'Lote creado por edición de compra');
                }
            }
            $gastos = (float) $compra->gastos()->sum('importe');
            $compra->update(['subtotal'=>round($subtotal, 2),'descuento_total'=>round($descuentoTotal, 2),'impuesto_total'=>round($impuestoTotal, 2),'total'=>round($subtotal-$descuentoTotal+$impuestoTotal+$gastos, 2)]);
            return $compra->fresh('detalles', 'gastos', 'proveedor', 'usuario');
        });
    }

    public function anular(Compra $compra, int $usuarioId): Compra
    {
        if ($compra->estado === 'anulada') {
            return $compra;
        }

        return DB::transaction(function () use ($compra, $usuarioId) {
            $compra = Compra::whereKey($compra->id)->lockForUpdate()->firstOrFail();
            if ($compra->estado === 'anulada') {
                return $compra;
            }
            if ($compra->pagos()->where('estado', 'registrado')->exists()) {
                throw new InvalidArgumentException('La compra tiene pagos registrados. Anule o regularice los pagos antes de anularla.');
            }

            $compra->load('detalles.lote', 'detalles.producto');
            foreach ($compra->detalles as $detalle) {
                $this->retirarLoteDeCompra($detalle->lote);
                $this->inventory->registrarMovimiento(
                    $detalle->producto,
                    'salida',
                    (float) $detalle->cantidad,
                    'compra',
                    $compra->id,
                    $usuarioId,
                    'Salida por anulacion de compra',
                    null,
                    true
                );
            }

            $compra->update(['estado' => 'anulada']);

            return $compra;
        });
    }

    public function eliminarDefinitivamente(Compra $compra): array
    {
        return DB::transaction(function () use ($compra) {
            $compra = Compra::whereKey($compra->id)->lockForUpdate()->firstOrFail();
            $compra->load('detalles.lote.consumos', 'detalles.producto');

            if ($compra->pagos()->where('estado', 'registrado')->exists()) {
                throw new InvalidArgumentException('La compra todavía tiene pagos registrados. Anule o regularice los pagos antes de eliminarla definitivamente.');
            }

            $before = $compra->only([
                'id', 'numero_compra', 'tipo_documento', 'nombre_comercial', 'fecha', 'factura', 'total', 'estado',
            ]);
            $productosAfectados = $compra->detalles->pluck('producto_id')->unique()->filter()->values();

            foreach ($compra->detalles as $detalle) {
                $lote = $detalle->lote;
                if ($lote && $lote->consumos->isNotEmpty()) {
                    $lote->update([
                        'estado' => 'inactivo',
                        'cantidad_disponible' => 0,
                        'compra_id' => null,
                        'detalle_compra_id' => null,
                        'observacion' => trim(($lote->observacion ? $lote->observacion . "\n" : '') . 'Compra eliminada; se conserva el lote por trazabilidad.'),
                    ]);
                } elseif ($lote) {
                    $lote->delete();
                }
            }

            MovimientoInventario::where('referencia_tipo', 'compra')
                ->where('referencia_id', $compra->id)
                ->delete();

            $compra->pagos()->delete();
            $compra->cuotas()->delete();
            $compra->delete();

            foreach ($productosAfectados as $productoId) {
                $this->recalcularMovimientosProducto((int) $productoId);
            }

            return $before;
        });
    }

    private function retirarLoteDeCompra(?InventarioLote $lote): void
    {
        if (! $lote) {
            return;
        }

        $lote = InventarioLote::whereKey($lote->id)->lockForUpdate()->first();
        if (! $lote) {
            return;
        }

        $lote->update([
            'estado' => 'inactivo',
            'cantidad_disponible' => 0,
            'observacion' => trim(($lote->observacion ? $lote->observacion . "\n" : '') . 'Compra anulada; lote retirado de disponibilidad.'),
        ]);
    }

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

    private function registrarCuotas(Compra $compra, array $cuotas): void
    {
        if (count($cuotas) < 2) {
            throw new InvalidArgumentException('Para pago en cuotas registre por lo menos dos vencimientos.');
        }
        $totalCentavos = (int) round((float) $compra->total * 100);
        $cuotasCentavos = 0;
        $fechaAnterior = null;
        foreach ($cuotas as $cuota) {
            $fecha = $cuota['fecha_vencimiento'] ?? null;
            $importeCentavos = (int) round((float) ($cuota['importe'] ?? 0) * 100);
            if (! $fecha || $importeCentavos <= 0 || ($fechaAnterior && $fecha < $fechaAnterior)) {
                throw new InvalidArgumentException('Revise las fechas e importes del calendario de cuotas.');
            }
            $fechaAnterior = $fecha;
            $cuotasCentavos += $importeCentavos;
        }
        if ($cuotasCentavos !== $totalCentavos) {
            throw new InvalidArgumentException('La suma de las cuotas debe ser igual al total de la compra.');
        }
        foreach (array_values($cuotas) as $index => $cuota) {
            $compra->cuotas()->create([
                'numero' => $index + 1,
                'fecha_vencimiento' => $cuota['fecha_vencimiento'],
                'importe' => $cuota['importe'],
                'observacion' => $cuota['observacion'] ?? null,
            ]);
        }
    }
}
