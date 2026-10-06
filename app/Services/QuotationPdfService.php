<?php

namespace App\Services;

use App\Models\Cotizacion;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotationPdfService
{
    public function data(Cotizacion $cotizacion): array
    {
        $isSupra = str_contains(strtolower((string) $cotizacion->nombre_comercial), 'supra');
        $brand = $isSupra ? 'supra-medic' : 'san-benjamin';
        $logo = public_path('assets/logos/' . $brand . '.jpeg');
        [$width, $height] = getimagesize($logo);
        $scale = min(160 / $width, 88 / $height);

        return [
            'cotizacion' => $cotizacion,
            'isSupra' => $isSupra,
            'profile' => config('quotation_pdf.' . $brand),
            'footer' => $this->footer($cotizacion, $brand),
            // Contact details are shared; the commercial identity is not.
            'header' => array_replace(config('quotation_pdf.san-benjamin'), [
                'name' => config('quotation_pdf.' . $brand . '.name'),
            ]),
            'logo' => $logo,
            'logoWidth' => $width * $scale,
            'logoHeight' => $height * $scale,
            'signature' => resource_path('assets/signatures/' . $brand . '.jpeg'),
        ];
    }

    public function render(Cotizacion $cotizacion): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('cotizaciones.pdf', $this->data($cotizacion))->setPaper('letter');
    }

    private function footer(Cotizacion $cotizacion, string $brand): array
    {
        $defaults = config('quotation_pdf.footer_defaults');
        $company = config("quotation_pdf.{$brand}.footer_company");
        $deposit = str_replace('{empresa}', $company, $defaults['deposito_cuenta']);

        return [
            'empresa' => $company,
            'direccion' => trim((string) ($cotizacion->footer_direccion ?: $defaults['direccion'])),
            'validez' => trim((string) ($cotizacion->footer_validez ?: $defaults['validez'])),
            'entrega' => trim((string) ($cotizacion->footer_entrega ?: $defaults['entrega'])),
            'condiciones_pago' => trim((string) ($cotizacion->footer_condiciones_pago ?: $defaults['condiciones_pago'])),
            'deposito_cuenta' => trim((string) ($cotizacion->footer_deposito_cuenta ?: $deposit)),
        ];
    }
}
