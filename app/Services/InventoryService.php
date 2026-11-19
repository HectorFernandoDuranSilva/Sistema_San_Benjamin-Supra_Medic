<?php

namespace App\Services;

use App\Models\DetalleVenta;
use App\Models\InventarioLote;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function stockActual(Producto|int $producto): float
    {
        $productoId = $producto instanceof Producto ? $producto->id : $producto;

        return (float) (MovimientoInventario::where('producto_id', $productoId)
            ->latest('fecha_movimiento')
            ->latest('id')
            ->value('stock_nuevo') ?? 0);
    }

    public function registrarMovimiento(
        Producto $producto,
        string $tipo,
        float $cantidad,
        ?string $referenciaTipo,
        ?int $referenciaId,
        ?int $usuarioId,
        ?string $observacion = null,
        ?float $costoEntrada = null,
        bool $permitirStockNegativo = false
    ): MovimientoInventario {
        // Keep the authenticated operator attached even when a caller does
        // not pass the id explicitly.
        $usuarioId ??= auth()->id();

        if (! is_finite($cantidad) || $cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }
        if ($costoEntrada !== null && (! is_finite($costoEntrada) || $costoEntrada < 0)) {
            throw new InvalidArgumentException('El costo de entrada no puede ser negativo.');
        }

        return DB::transaction(function () use ($producto, $tipo, $cantidad, $referenciaTipo, $referenciaId, $usuarioId, $observacion, $costoEntrada, $permitirStockNegativo) {
            $producto = Producto::whereKey($producto->id)->lockForUpdate()->firstOrFail();
            $stockAnterior = $this->stockActual($producto);
            $stockNuevo = match ($tipo) {
                'entrada', 'ajuste_positivo', 'devolucion' => $stockAnterior + $cantidad,
                'salida', 'ajuste_negativo' => $stockAnterior - $cantidad,
                'anulacion' => $stockAnterior + $cantidad,
                default => throw new InvalidArgumentException('Tipo de movimiento no soportado.'),
            };

            if ($stockNuevo < 0 && ! $permitirStockNegativo && in_array($tipo, ['salida', 'ajuste_negativo'], true)) {
                throw new InvalidArgumentException("Stock insuficiente para {$producto->codigo} - {$producto->nombre}.");
            }

            $entrada = $stockNuevo > $stockAnterior;
            $valorAnterior = $stockAnterior == 0 ? 0.0 : ($producto->valor_inventario === null ? null : (float) $producto->valor_inventario);
            $costo = $entrada ? $costoEntrada : ($valorAnterior !== null && $stockAnterior > 0 ? $valorAnterior / $stockAnterior : null);
            // Restore a cancelled sale at its recorded outgoing cost, never today's price.
            if ($tipo === 'anulacion' && $referenciaTipo === 'venta') {
                $salidas = MovimientoInventario::where('producto_id', $producto->id)
                    ->where('referencia_tipo', 'venta')->where('referencia_id', $referenciaId)
                    ->where('tipo_movimiento', 'salida')->get();
                $costo = $salidas->isNotEmpty() && $salidas->every(fn ($m) => $m->costo_valoracion !== null)
                    ? $salidas->sum(fn ($m) => (float) $m->valor_anterior - (float) $m->valor_nuevo) / $salidas->sum('cantidad') : null;
            }
            $valorNuevo = $valorAnterior !== null && $costo !== null
                ? round($valorAnterior + ($entrada ? 1 : -1) * $cantidad * $costo, 6) : null;
            // An incoming purchase that covers a delivery without stock restores valuation for the remaining units.
            if ($entrada && $stockAnterior < 0 && $stockNuevo > 0 && $costoEntrada !== null) {
                $valorNuevo = round($stockNuevo * $costoEntrada, 6);
            }
            if ($stockNuevo == 0) {
                $valorNuevo = 0.0;
            }
            $producto->forceFill([
                'valor_inventario' => $valorNuevo,
                'costo_promedio' => $stockNuevo > 0 && $valorNuevo !== null ? round($valorNuevo / $stockNuevo, 6) : null,
            ])->save();

            return MovimientoInventario::create([
                'producto_id' => $producto->id,
                'tipo_movimiento' => $tipo,
                'cantidad' => $cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'referencia_tipo' => $referenciaTipo,
                'referencia_id' => $referenciaId,
                'observacion' => $observacion,
                'usuario_id' => $usuarioId,
                'fecha_movimiento' => now(),
                'costo_valoracion' => $costo,
                'valor_anterior' => $valorAnterior,
                'valor_nuevo' => $valorNuevo,
            ]);
        });
    }

    /**
     * Applies a real incoming quantity to the oldest documented sale shortages.
     * The movement remains the source of truth for stock; this only closes the
     * per-document pending quantity so dashboards and document detail agree.
     */
    public function aplicarEntradaAFaltantes(Producto $producto, float $cantidad): float
    {
        if (! is_finite($cantidad) || $cantidad <= 0) {
            return 0.0;
        }

        return DB::transaction(function () use ($producto, $cantidad): float {
            $productoBloqueado = Producto::whereKey($producto->id)->lockForUpdate()->firstOrFail();
            $pendienteAplicar = round($cantidad, 2);
            $aplicado = 0.0;

            $detalles = DetalleVenta::query()
                ->where('producto_id', $productoBloqueado->id)
                ->where('faltante_stock', '>', 0)
                ->whereHas('venta', fn ($query) => $query->where('estado', 'registrada'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($detalles as $detalle) {
                if ($pendienteAplicar <= 0) {
                    break;
                }

                $faltante = (float) $detalle->faltante_stock;
                $regularizado = min($pendienteAplicar, $faltante);
                $detalle->forceFill([
                    'faltante_stock' => round($faltante - $regularizado, 2),
                ])->save();
                $pendienteAplicar = round($pendienteAplicar - $regularizado, 2);
                $aplicado = round($aplicado + $regularizado, 2);
            }

            // Clear the operational pending flag only after every detail of
            // the document has been covered. Audit history and movements stay
            // unchanged for traceability.
            $ventaIds = $detalles->pluck('venta_id')->unique()->filter()->values();
            if ($ventaIds->isNotEmpty()) {
                $ventas = Venta::whereIn('id', $ventaIds)
                    ->where('permitir_salida_sin_stock', true)
                    ->lockForUpdate()
                    ->get();

                foreach ($ventas as $venta) {
                    $tienePendientes = DetalleVenta::where('venta_id', $venta->id)
                        ->where('faltante_stock', '>', 0)
                        ->exists();

                    if (! $tienePendientes) {
                        $venta->forceFill(['permitir_salida_sin_stock' => false])->save();
                    }
                }
            }

            return $aplicado;
        });
    }

    public function registrarLoteEntrada(
        Producto $producto,
        float $cantidad,
        ?int $compraId,
        ?int $detalleCompraId,
        float $costoUnitario,
        ?string $fechaVencimiento,
        ?string $codigoLote,
        ?int $usuarioId,
        ?string $observacion = null
    ): InventarioLote {
        if (! is_finite($cantidad) || $cantidad < 0) {
            throw new InvalidArgumentException('La cantidad del lote no puede ser negativa.');
        }

        if ($producto->maneja_lote_universal) {
            return $this->registrarEntradaEnLoteUniversal($producto, $cantidad, $costoUnitario, $usuarioId);
        }

        return InventarioLote::create([
            'producto_id' => $producto->id,
            'compra_id' => $compraId,
            'detalle_compra_id' => $detalleCompraId,
            'codigo_lote' => $codigoLote ?: null,
            'fecha_vencimiento' => $fechaVencimiento ?: null,
            'cantidad_inicial' => $cantidad,
            'cantidad_disponible' => $cantidad,
            'costo_unitario' => $costoUnitario,
            'estado' => 'activo',
            'observacion' => $observacion,
            'usuario_id' => $usuarioId,
        ]);
    }

    private function registrarEntradaEnLoteUniversal(
        Producto $producto,
        float $cantidad,
        float $costoUnitario,
        ?int $usuarioId
    ): InventarioLote {
        $lotes = InventarioLote::where('producto_id', $producto->id)
            ->where('estado', 'activo')
            ->withSum('consumos as consumo_real', 'cantidad')
            ->lockForUpdate()
            ->get();

        $universal = $lotes->firstWhere('codigo_lote', 'UNIVERSAL');
        $otros = $lotes->filter(fn (InventarioLote $lote): bool => $lote->id !== $universal?->id);
        $cantidadInicialAnterior = (float) $lotes->sum(fn (InventarioLote $lote): float => (float) $lote->cantidad_inicial);
        $cantidadDisponibleAnterior = (float) $lotes->sum(fn (InventarioLote $lote): float => (float) $lote->cantidad_disponible);
        $consumoRealAnterior = (float) $lotes->sum(fn (InventarioLote $lote): float => (float) ($lote->consumo_real ?? 0));
        $cantidadInicial = round($cantidadInicialAnterior + $cantidad, 2);
        $cantidadDisponible = round($cantidadDisponibleAnterior + $cantidad, 2);
        $consumidoAjustado = max(0, round($cantidadInicial - $cantidadDisponible - $consumoRealAnterior, 2));
        $costoBase = (float) $lotes->sum(fn (InventarioLote $lote): float => (float) $lote->cantidad_inicial * (float) $lote->costo_unitario);
        $costoUnitarioFinal = $cantidadInicial > 0
            ? round(($costoBase + ($cantidad * $costoUnitario)) / $cantidadInicial, 4)
            : $costoUnitario;

        if ($universal) {
            $universal->forceFill([
                'cantidad_inicial' => $cantidadInicial,
                'cantidad_disponible' => $cantidadDisponible,
                'cantidad_consumida_ajustada' => $consumidoAjustado,
                'costo_unitario' => $costoUnitarioFinal,
                'fecha_vencimiento' => null,
                'observacion' => 'Lote universal acumulado para producto sin vencimiento.',
                'usuario_id' => $usuarioId ?? auth()->id(),
            ])->save();
        } else {
            $universal = InventarioLote::create([
                'producto_id' => $producto->id,
                'compra_id' => null,
                'detalle_compra_id' => null,
                'codigo_lote' => 'UNIVERSAL',
                'fecha_vencimiento' => null,
                'cantidad_inicial' => $cantidadInicial,
                'cantidad_disponible' => $cantidadDisponible,
                'cantidad_consumida_ajustada' => $consumidoAjustado,
                'costo_unitario' => $costoUnitarioFinal,
                'estado' => 'activo',
                'observacion' => 'Lote universal acumulado para producto sin vencimiento.',
                'usuario_id' => $usuarioId ?? auth()->id(),
            ]);
        }

        foreach ($otros as $lote) {
            $lote->forceFill([
                'estado' => 'inactivo',
                'observacion' => trim(($lote->observacion ? $lote->observacion . "\n" : '') . 'Consolidado en lote universal; se conserva por trazabilidad.'),
            ])->save();
        }

        return $universal;
    }

    /**
     * Documents a lot supplied on a sale when the lot is not yet registered.
     * This is deliberately not an inventory entry: the sale movement remains
     * the only source that changes the product's aggregate stock.
     */
    public function registrarLoteManualDeVenta(
        Producto $producto,
        float $cantidad,
        ?string $fechaVencimiento,
        string $codigoLote,
        ?int $usuarioId,
        bool $stockPendiente = false
    ): InventarioLote {
        $codigoLote = trim($codigoLote);
        if ($codigoLote === '') {
            throw new InvalidArgumentException('El lote manual no puede estar vacío.');
        }

        $existente = InventarioLote::where('producto_id', $producto->id)
            ->where('codigo_lote', $codigoLote)
            ->first();

        if ($existente) {
            return $existente;
        }

        $cantidadDocumentada = $stockPendiente ? 0.0 : round($cantidad, 2);

        return InventarioLote::create([
            'producto_id' => $producto->id,
            'compra_id' => null,
            'detalle_compra_id' => null,
            'codigo_lote' => $codigoLote,
            'fecha_vencimiento' => $fechaVencimiento ?: null,
            'cantidad_inicial' => $cantidadDocumentada,
            'cantidad_disponible' => 0.0,
            'cantidad_consumida_ajustada' => $cantidadDocumentada,
            'costo_unitario' => (float) ($producto->costo_promedio ?? $producto->costo_referencial ?? 0),
            'estado' => 'activo',
            'observacion' => $stockPendiente
                ? 'Lote manual documentado desde venta con stock pendiente.'
                : 'Lote manual documentado desde venta; no genera entrada de stock.',
            'usuario_id' => $usuarioId ?? auth()->id(),
        ]);
    }

    public function stockDisponibleParaVenta(Producto $producto): float
    {
        $lotes = InventarioLote::where('producto_id', $producto->id)
            ->where('estado', 'activo');
        $lotesActivos = (clone $lotes)->get();

        if ($lotesActivos->isEmpty()) {
            return max(0, $this->stockActual($producto));
        }

        $stockVigenteEnLotes = (clone $lotes)
            ->where('estado', 'activo')
            ->where('cantidad_disponible', '>', 0)
            ->where(fn (Builder $query) => $query
                ->whereNull('fecha_vencimiento')
                ->orWhereDate('fecha_vencimiento', '>=', today()))
            ->sum('cantidad_disponible');

        // Purchases entered without a lot still exist in the movement ledger.
        // Add that balance instead of silently dropping it when other lots
        // also exist for the same product.
        $stockSinLote = max(0, round(
            $this->stockActual($producto) - (float) $lotesActivos->sum('cantidad_disponible'),
            2
        ));

        return max(0, round((float) $stockVigenteEnLotes + $stockSinLote, 2));
    }

    public function stockSinLote(Producto $producto): float
    {
        $lotesActivos = InventarioLote::where('producto_id', $producto->id)
            ->where('estado', 'activo')
            ->sum('cantidad_disponible');

        return max(0, round($this->stockActual($producto) - (float) $lotesActivos, 2));
    }

    /**
     * Keeps the product balance aligned with its active lot balances while
     * preserving outstanding stock shortages. This is intentionally called
     * after a lot is edited, where historical duplicate lot codes are common.
     */
    public function sincronizarStockDesdeLotes(Producto $producto, int $usuarioId, string $observacion = 'Reconciliación de saldo de lotes'): void
    {
        $lotesActivos = InventarioLote::where('producto_id', $producto->id)
            ->where('estado', 'activo')
            ->get();

        if ($lotesActivos->isEmpty()) {
            return;
        }

        $stockFisico = (float) $lotesActivos->sum('cantidad_disponible') + $this->stockSinLote($producto);
        $faltantePendiente = Schema::hasColumn('detalle_ventas', 'faltante_stock')
            ? (float) DetalleVenta::query()
                ->where('producto_id', $producto->id)
                ->where('faltante_stock', '>', 0)
                ->whereHas('venta', fn ($query) => $query->where('estado', 'registrada'))
                ->sum('faltante_stock')
            : 0.0;
        $saldoEsperado = round($stockFisico - $faltantePendiente, 2);
        $saldoActual = round($this->stockActual($producto), 2);
        $diferencia = round($saldoEsperado - $saldoActual, 2);

        if (abs($diferencia) <= 0.01) {
            return;
        }

        $this->registrarMovimiento(
            $producto,
            $diferencia > 0 ? 'ajuste_positivo' : 'ajuste_negativo',
            abs($diferencia),
            'lote_reconciliacion',
            $producto->id,
            $usuarioId,
            $observacion,
            null,
            true
        );
    }

    public function consumirLotesPorVencimiento(
        Producto $producto,
        DetalleVenta $detalleVenta,
        float $cantidad,
        ?int $lotePreferidoId = null,
        ?string $fechaVencimientoManual = null,
        bool $permitirFaltante = false,
        bool $soloLoteManual = false
    ): float
    {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad a consumir debe ser mayor a cero.');
        }

        $pendiente = $cantidad;
        $lotePreferido = null;

        if ($lotePreferidoId) {
            $lotePreferido = InventarioLote::whereKey($lotePreferidoId)
                ->where('producto_id', $producto->id)
                ->where('estado', 'activo')
                ->where('cantidad_disponible', '>', 0)
                ->where(fn (Builder $query) => $query
                    ->whereNull('fecha_vencimiento')
                    ->orWhereDate('fecha_vencimiento', '>=', today()))
                ->lockForUpdate()
                ->first();

            // A consolidated lot can point to a historical row that was
            // exhausted after the form was opened. Recover the usable sibling
            // with the same lot code and expiration before rejecting the sale.
            if (! $lotePreferido) {
                $solicitado = InventarioLote::whereKey($lotePreferidoId)
                    ->where('producto_id', $producto->id)
                    ->first();

                if ($solicitado) {
                    $lotePreferido = InventarioLote::where('producto_id', $producto->id)
                        ->where('estado', 'activo')
                        ->where('cantidad_disponible', '>', 0)
                        ->where('codigo_lote', $solicitado->codigo_lote)
                        ->when(
                            $solicitado->fecha_vencimiento,
                            fn (Builder $query) => $query->whereDate('fecha_vencimiento', $solicitado->fecha_vencimiento),
                            fn (Builder $query) => $query->whereNull('fecha_vencimiento'),
                        )
                        ->where(fn (Builder $query) => $query
                            ->whereNull('fecha_vencimiento')
                            ->orWhereDate('fecha_vencimiento', '>=', today()))
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->first();
                }
            }

            if (! $lotePreferido && ! $permitirFaltante) {
                throw new InvalidArgumentException("El lote seleccionado no está disponible o está vencido para {$producto->codigo} - {$producto->nombre}.");
            }

            if ($lotePreferido) {
                $pendiente = $this->consumirLote($lotePreferido, $detalleVenta, $pendiente, $fechaVencimientoManual);
            }
        }

        $lotes = InventarioLote::where('producto_id', $producto->id)
            ->where('estado', 'activo')
            ->where('cantidad_disponible', '>', 0)
            ->where(fn (Builder $query) => $query
                ->whereNull('fecha_vencimiento')
                ->orWhereDate('fecha_vencimiento', '>=', today()))
            ->when($lotePreferido, fn ($query) => $query->whereKeyNot($lotePreferido->id))
            ->orderByRaw('CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END')
            ->orderBy('fecha_vencimiento')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($soloLoteManual) {
            $lotes = collect();
        }

        foreach ($lotes as $lote) {
            if ($pendiente <= 0) {
                break;
            }

            $pendiente = $this->consumirLote($lote, $detalleVenta, $pendiente);
        }

        if ($pendiente > 0 && $this->productoTieneLotesActivos($producto) && ! $permitirFaltante) {
            throw new InvalidArgumentException("No hay lotes vigentes suficientes para {$producto->codigo} - {$producto->nombre}.");
        }

        return $this->productoTieneLotesActivos($producto) ? $pendiente : 0.0;
    }

    private function productoTieneLotesActivos(Producto $producto): bool
    {
        return InventarioLote::where('producto_id', $producto->id)
            ->where('estado', 'activo')
            ->where('cantidad_disponible', '>', 0)
            ->exists();
    }

    private function consumirLote(
        InventarioLote $lote,
        DetalleVenta $detalleVenta,
        float $pendiente,
        ?string $fechaVencimientoManual = null
    ): float {
        $disponible = (float) $lote->cantidad_disponible;
        $consumo = min($pendiente, $disponible);

        if ($consumo <= 0) {
            return $pendiente;
        }

        $lote->forceFill([
            'cantidad_disponible' => round($disponible - $consumo, 2),
        ])->save();

        $detalleVenta->lotes()->create([
            'inventario_lote_id' => $lote->id,
            'cantidad' => $consumo,
            'fecha_vencimiento' => $fechaVencimientoManual ?: $lote->fecha_vencimiento,
        ]);

        return round($pendiente - $consumo, 2);
    }

    public function devolverLotesDeVenta(DetalleVenta $detalleVenta): void
    {
        $detalleVenta->loadMissing('lotes.lote');

        foreach ($detalleVenta->lotes as $consumo) {
            $lote = $consumo->lote;

            if (! $lote) {
                continue;
            }

            $lote->forceFill([
                'cantidad_disponible' => round((float) $lote->cantidad_disponible + (float) $consumo->cantidad, 2),
                'estado' => 'activo',
            ])->save();
        }
    }
}
