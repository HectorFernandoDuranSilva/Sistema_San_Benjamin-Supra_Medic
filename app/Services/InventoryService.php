<?php

namespace App\Services;

use App\Models\DetalleVenta;
use App\Models\InventarioLote;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
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
        if (!is_finite($cantidad) || $cantidad <= 0 || ($costoEntrada !== null && (!is_finite($costoEntrada) || $costoEntrada <= 0))) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
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

            if ($stockNuevo < 0 && ! $permitirStockNegativo) {
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
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad del lote debe ser mayor a cero.');
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

    public function stockDisponibleParaVenta(Producto $producto): float
    {
        $lotesActivos = InventarioLote::where('producto_id', $producto->id)
            ->where('estado', 'activo')
            ->exists();

        if (! $lotesActivos) {
            return $this->stockActual($producto);
        }

        return (float) InventarioLote::where('producto_id', $producto->id)
            ->where('estado', 'activo')
            ->where('cantidad_disponible', '>', 0)
            ->where(fn (Builder $query) => $query
                ->whereNull('fecha_vencimiento')
                ->orWhereDate('fecha_vencimiento', '>=', today()))
            ->sum('cantidad_disponible');
    }

    public function consumirLotesPorVencimiento(
        Producto $producto,
        DetalleVenta $detalleVenta,
        float $cantidad,
        ?int $lotePreferidoId = null,
        ?string $fechaVencimientoManual = null,
        bool $permitirFaltante = false
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

            if (! $lotePreferido) {
                throw new InvalidArgumentException("El lote seleccionado no está disponible o está vencido para {$producto->codigo} - {$producto->nombre}.");
            }

            $pendiente = $this->consumirLote($lotePreferido, $detalleVenta, $pendiente, $fechaVencimientoManual);
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
