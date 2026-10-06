<?php

namespace App\Services;

use App\Models\Producto;
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

                if ($this->inventory->stockDisponibleParaVenta($producto) < $cantidad) {
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
                    $item['fecha_vencimiento'] ?? null
                );
                if ($pendienteLotes > 0) {
                    throw new InvalidArgumentException("No hay lotes vigentes suficientes para {$producto->codigo} - {$producto->nombre}.");
                }
                $this->inventory->registrarMovimiento($producto, 'salida', $cantidad, 'venta', $venta->id, $usuarioId, 'Salida por venta');
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

    private function siguienteNumero(): string
    {
        $next = ((int) Venta::max('id')) + 1;

        return 'V-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function lineAmount(array $item, string $key, float $default = 0): float
    {
        return max(0, (float) ($item[$key] ?? $default));
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
