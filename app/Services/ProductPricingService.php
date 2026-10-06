<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\TipoCambio;

class ProductPricingService
{
    public function calculate(float $precioIngresoUsd, float $tipoCambio, float $porcentajeUtilidad): array
    {
        $costoReferencial = round($precioIngresoUsd * $tipoCambio, 2);

        return [
            'costo_referencial' => $costoReferencial,
            'precio_venta' => round($costoReferencial * (1 + ($porcentajeUtilidad / 100)), 2),
        ];
    }

    public function suggestedReplacementPrice(Producto $producto, ?float $rate = null): ?float
    {
        $rate ??= TipoCambio::ultimoValor();
        $margin = (float) ($producto->porcentaje_utilidad ?? 0);

        if (($producto->moneda_costo ?? 'usd') === 'usd') {
            $usd = (float) ($producto->precio_ingreso_usd ?? 0);

            if ($usd <= 0 || $rate <= 0) {
                return null;
            }

            return $this->calculate($usd, $rate, $margin)['precio_venta'];
        }

        $costBs = (float) ($producto->costo_referencial ?? 0);

        return $costBs > 0 ? round($costBs * (1 + ($margin / 100)), 2) : null;
    }

    public function replacementCost(Producto $producto, ?float $rate = null): float
    {
        $rate ??= TipoCambio::ultimoValor();

        if (($producto->moneda_costo ?? 'usd') === 'usd') {
            return round((float) ($producto->precio_ingreso_usd ?? 0) * $rate, 2);
        }

        return round((float) ($producto->costo_referencial ?? 0), 2);
    }

    public function refreshProductsForRate(TipoCambio $tipoCambio): int
    {
        $updated = 0;
        $rate = (float) $tipoCambio->valor;

        Producto::query()
            ->orderBy('id')
            ->chunkById(100, function ($productos) use ($rate, &$updated): void {
                foreach ($productos as $producto) {
                    $values = ['tipo_cambio' => $rate];

                    if (($producto->moneda_costo ?? 'usd') === 'usd' && (float) $producto->precio_ingreso_usd > 0) {
                        $values['costo_referencial'] = $this->calculate(
                            (float) $producto->precio_ingreso_usd,
                            $rate,
                            (float) $producto->porcentaje_utilidad
                        )['costo_referencial'];
                    }

                    $producto->forceFill($values)->save();

                    $updated++;
                }
            });

        return $updated;
    }
}
