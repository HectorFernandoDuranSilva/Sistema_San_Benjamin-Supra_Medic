<?php

namespace App\Services;

use App\Models\TipoCambio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class BcbExchangeRateService
{
    public function updateFromBcb(): TipoCambio
    {
        $url = config('services.bcb.exchange_rate_url', 'https://www.bcb.gob.bo/librerias/indicadores/otras/ultimo.php');

        try {
            $response = Http::timeout((int) config('services.bcb.timeout', 15))
                ->withOptions(['verify' => (bool) config('services.bcb.verify_ssl', false)])
                ->retry((int) config('services.bcb.retries', 2), (int) config('services.bcb.retry_sleep_ms', 500))
                ->get($url);

            if (! $response->successful()) {
                throw new RuntimeException('No se pudo consultar la página del BCB.');
            }

            $valor = $this->extractOfficialExchangeRate($response->body());
            $this->assertReasonableRate($valor);

            return $this->storeRate($valor, 'BCB', $url, 'Actualizado automaticamente desde el bloque Tipo de cambio oficial del BCB.');
        } catch (Throwable $exception) {
            return $this->storeFallbackRate($url, $exception);
        }
    }

    public function extractOfficialExchangeRate(string $html): float
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        $normalized = $this->normalize($text);

        if (preg_match('/ESTADOS UNIDOS\s+DOLAR\s+USD\s+(\d{1,3}(?:[.,]\d{2,5})?)/iu', $normalized, $match)) {
            return $this->number($match[1]);
        }

        if (! preg_match('/Tipo de cambio oficial\s+Bolivianos por dolar estadounidense.*?(\d{1,3}(?:[.,]\d{2,5}))/iu', $normalized, $match)) {
            throw new RuntimeException('No se encontró el tipo de cambio oficial en la página del BCB.');
        }

        return $this->number($match[1]);
    }

    private function normalize(string $value): string
    {
        $search = ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú'];
        $replace = ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U'];

        return str_replace($search, $replace, $value);
    }

    private function number(string $value): float
    {
        $normalized = trim($value);

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        return (float) $normalized;
    }

    private function assertReasonableRate(float $valor): void
    {
        if ($valor < 1 || $valor > 30) {
            throw new RuntimeException('El tipo de cambio recibido del BCB está fuera del rango esperado.');
        }
    }

    private function storeFallbackRate(string $url, Throwable $exception): TipoCambio
    {
        $lastKnown = TipoCambio::where('estado', 'vigente')
            ->latest('fecha')
            ->latest('id')
            ->first();

        $fallback = (float) ($lastKnown?->valor ?? config('services.bcb.fallback', 6.96));
        $source = $lastKnown ? 'BCB respaldo local' : 'Respaldo configurado';

        return $this->storeRate(
            $fallback,
            $source,
            $url,
            'No se pudo actualizar desde el BCB. Se mantiene un valor de respaldo para no interrumpir el sistema. Motivo: ' . $exception->getMessage()
        );
    }

    private function storeRate(float $valor, string $fuente, string $url, string $observacion): TipoCambio
    {
        return TipoCambio::updateOrCreate(
            ['fecha' => today()],
            [
                'valor' => $valor,
                'moneda_origen' => 'USD',
                'moneda_destino' => 'BOB',
                'fuente' => $fuente,
                'url_fuente' => $url,
                'estado' => 'vigente',
                'observacion' => $observacion,
                'consultado_en' => now(),
            ]
        );
    }
}
