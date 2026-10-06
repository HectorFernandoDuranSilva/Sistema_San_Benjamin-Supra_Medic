<?php

namespace App\Services;

use App\Models\Compra;
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
            throw new InvalidArgumentException('La compra debe tener al menos un producto.');
        }

        return DB::transaction(function () use ($data, $items, $usuarioId) {
            $subtotal = 0;
            $impuestoTotal = 0;
            $productos = Producto::whereIn('id', $items->pluck('producto_id'))->lockForUpdate()->get()->keyBy('id');

            foreach ($items as $item) {
                $producto = $productos->get((int) $item['producto_id']);
                $cantidad = (float) $item['cantidad'];
                $costo = (float) $item['costo_unitario'];
                $porcentajeUtilidad = (float) ($item['porcentaje_utilidad'] ?? 27);
                $impuesto = (float) ($item['impuesto'] ?? 0);

                if (! $producto || $cantidad <= 0 || $costo <= 0 || $impuesto < 0 || $porcentajeUtilidad < 25 || $porcentajeUtilidad > 30) {
                    throw new InvalidArgumentException('Detalle de compra inválido: revise cantidad, costo, impuesto y utilidad.');
                }

                $subtotal += $cantidad * $costo;
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
                'impuesto_total' => $impuestoTotal,
                'total' => $subtotal + $impuestoTotal,
                'estado' => 'registrada',
                'observacion' => $data['observacion'] ?? null,
                'usuario_id' => $usuarioId,
            ]);

            foreach ($items as $item) {
                $producto = $productos->get((int) $item['producto_id']);
                $cantidad = (float) $item['cantidad'];
                $costo = (float) $item['costo_unitario'];
                $porcentajeUtilidad = (float) ($item['porcentaje_utilidad'] ?? 27);
                $impuesto = (float) ($item['impuesto'] ?? 0);
                $codigoLote = trim((string) ($item['codigo_lote'] ?? '')) ?: null;
                $subtotalDetalle = $cantidad * $costo;

                $detalle = $compra->detalles()->create([
                    'producto_id' => $producto->id,
                    'codigo_producto' => $producto->codigo,
                    'nombre_producto' => $producto->nombre,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costo,
                    'porcentaje_utilidad' => $porcentajeUtilidad,
                    'codigo_lote' => $codigoLote,
                    'fecha_vencimiento' => $item['fecha_vencimiento'] ?? null,
                    'subtotal' => $subtotalDetalle,
                    'impuesto' => $impuesto,
                    'total' => $subtotalDetalle + $impuesto,
                ]);

                $producto->forceFill([
                    'ultimo_costo_compra' => $costo,
                    'porcentaje_utilidad' => $porcentajeUtilidad,
                ])->save();

                $this->inventory->registrarMovimiento($producto, 'entrada', $cantidad, 'compra', $compra->id, $usuarioId, 'Entrada por compra', $costo);
                $this->inventory->registrarLoteEntrada(
                    $producto,
                    $cantidad,
                    $compra->id,
                    $detalle->id,
                    $costo,
                    $item['fecha_vencimiento'] ?? null,
                    $codigoLote,
                    $usuarioId,
                    'Lote creado por compra'
                );
            }

            return $compra->load('detalles', 'proveedor', 'usuario');
        });
    }

    private function siguienteNumero(): string
    {
        $next = ((int) Compra::max('id')) + 1;

        return 'C-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
