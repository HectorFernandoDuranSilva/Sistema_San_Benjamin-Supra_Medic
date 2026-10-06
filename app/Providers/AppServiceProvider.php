<?php

namespace App\Providers;

use App\Models\TipoCambio;
use App\Services\BcbExchangeRateService;
use App\Services\ProductPricingService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['auth.login', 'auth.select-commercial', 'dashboard'], function ($view): void {
            $tipoCambioActual = null;

            try {
                if (Schema::hasTable('tipos_cambio')) {
                    $todayRate = TipoCambio::where('estado', 'vigente')
                        ->whereDate('fecha', today())
                        ->latest('id')
                        ->first();

                    if ($this->shouldAutoUpdateExchangeRate($todayRate)) {
                        $updatedRate = app(BcbExchangeRateService::class)->updateFromBcb();
                        app(ProductPricingService::class)->refreshProductsForRate($updatedRate);
                    }

                    $tipoCambioActual = TipoCambio::where('estado', 'vigente')
                        ->latest('fecha')
                        ->latest('id')
                        ->first();
                }
            } catch (Throwable) {
                $tipoCambioActual = null;
            }

            $view->with('tipoCambioActual', $tipoCambioActual);
            $view->with('tipoCambioValor', $tipoCambioActual ? (float) $tipoCambioActual->valor : (float) config('services.bcb.fallback', 6.96));
        });
    }

    private function shouldAutoUpdateExchangeRate(?TipoCambio $todayRate): bool
    {
        if (! (bool) config('services.bcb.auto_update_on_web', true)) {
            return false;
        }

        if (app()->environment('testing') && ! (bool) config('services.bcb.force_auto_update_in_testing', false)) {
            return false;
        }

        if (! $todayRate || ! $todayRate->consultado_en) {
            return true;
        }

        $hours = max(1, (int) config('services.bcb.auto_update_interval_hours', 4));

        return $todayRate->consultado_en->lte(now()->subHours($hours));
    }
}
