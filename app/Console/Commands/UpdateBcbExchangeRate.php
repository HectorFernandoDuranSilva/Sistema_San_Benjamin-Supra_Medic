<?php

namespace App\Console\Commands;

use App\Services\BcbExchangeRateService;
use App\Services\ProductPricingService;
use Illuminate\Console\Command;
use Throwable;

class UpdateBcbExchangeRate extends Command
{
    protected $signature = 'bcb:actualizar-tipo-cambio {--sin-recalcular : Solo guarda el tipo de cambio, sin actualizar precios de productos.}';

    protected $description = 'Consulta el BCB y guarda el tipo de cambio oficial del dolar.';

    public function handle(BcbExchangeRateService $service, ProductPricingService $pricing): int
    {
        try {
            $tipoCambio = $service->updateFromBcb();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Tipo de cambio BCB actualizado: {$tipoCambio->valor} para {$tipoCambio->fecha->format('Y-m-d')}");

        if (! $this->option('sin-recalcular')) {
            $updated = $pricing->refreshProductsForRate($tipoCambio);
            $this->info("Productos recalculados por fluctuacion del dolar: {$updated}");
        }

        return self::SUCCESS;
    }
}
