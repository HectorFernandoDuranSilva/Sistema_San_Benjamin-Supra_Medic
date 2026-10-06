<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\Producto;
use App\Services\QuotationPdfService;
use Tests\TestCase;

class QuotationPdfTest extends TestCase
{
    private function quotation(string $brand, int $count): Cotizacion
    {
        $quote = new Cotizacion([
            'nombre_comercial' => $brand, 'numero_cotizacion' => 'CT-PRUEBA',
            'fecha' => '2026-09-10', 'valida_hasta' => '2026-09-15',
            'nit_cliente' => '123456',
            'total' => 470 * $count, 'descuento' => 0,
            'footer_validez' => '5 días calendario',
            'footer_entrega' => '10 días calendario, dependiendo al ítem y el stock en almacén.',
            'footer_condiciones_pago' => 'Transferencia bancaria',
        ]);
        $quote->setRelation('cliente', new Cliente(['nombre' => 'CLIENTE DE PRUEBA - CAMARGO']));
        $rows = collect();
        for ($i = 0; $i < $count; $i++) {
            $row = new DetalleCotizacion([
                'nombre_producto' => $i % 2 ? 'TUBOS CAPILARES CON HEPARINA' : 'TRANSAMINASA GPT',
                'cantidad' => 2, 'precio_unitario' => 235, 'total' => 470,
                'porcentaje_utilidad' => 27, 'impuesto' => 15, 'costo_referencial' => 185,
                'marca' => 'Bioclin', 'procedencia' => 'Brasil', 'fecha_vencimiento' => '2027-05-31',
            ]);
            $row->setRelation('producto', new Producto(['medida' => 'CAJA X 100']));
            $rows->push($row);
        }
        $quote->setRelation('detalles', $rows);

        return $quote;
    }

    public function test_brand_signatures_and_private_values_in_customer_pdf(): void
    {
        $service = app(QuotationPdfService::class);
        foreach (['San Benjamin' => 'san-benjamin', 'Supra Medic' => 'supra-medic'] as $brand => $slug) {
            $quote = $this->quotation($brand, 3);
            $before = $quote->toArray();
            $data = $service->data($quote);
            $this->assertStringEndsWith($slug . '.jpeg', $data['signature']);
            $this->assertFileExists($data['signature']);
            $html = view('cotizaciones.pdf', $data)->render();
            $expectedName = $slug === 'supra-medic' ? 'SUPRA MEDIC' : 'SAN BENJAMÍN S.R.L.';
            $this->assertSame($expectedName, $data['header']['name']);
            foreach (['DISTRIBUIDORA', $expectedName, 'Calle Grau N.º 661', 'TEL.: (591) (464) 42910', 'SUCRE - BOLIVIA'] as $headerLine) {
                $this->assertStringContainsString($headerLine, $html);
            }
            $expectedCompany = $slug === 'supra-medic' ? 'Distribuidora Supra Medic' : 'Distribuidora San Benjamín S.R.L.';
            foreach ([
                'Empresa:',
                $expectedCompany,
                'Dirección:',
                'Grau # 661: 464-39322 67632137',
                'Tiempo de validez de la oferta:',
                '5 días calendario',
                'Tiempo de entrega:',
                '10 días calendario, dependiendo al ítem y el stock en almacén.',
                'Condiciones de pago:',
                'Transferencia bancaria',
                'Depósito a cuenta:',
            ] as $footerLine) {
                $this->assertStringContainsString($footerLine, $html);
            }
            foreach (['Utilidad', 'Impuesto', 'costo_referencial', '27.00%'] as $private) {
                $this->assertStringNotContainsString($private, $html);
            }
            $this->assertStringContainsString('1,410.00', $html);
            $this->assertStringContainsString('PRESENTACIÓN', $html);
            foreach (['Sucre, 10 de septiembre de 2026', 'Bioclin', 'Brasil', '31/05/2027'] as $text) {
                $this->assertStringContainsString($text, $html);
            }
            $this->assertLessThan(strpos($html, 'PRESENTE.-'), strpos($html, 'NIT: 123456'));
            if ($slug === 'supra-medic') {
                $this->assertStringNotContainsString('san-benjamin', $html);
            }
            $pdf = $service->render($quote)->output();
            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertMatchesRegularExpression('/MediaBox\s*\[0\.000\s+0\.000\s+612\.000\s+792\.000\]/', $pdf);
            $this->assertSame($before, $quote->toArray());
            if ($folder = getenv('QUOTATION_PDF_QA')) {
                file_put_contents($folder . '/cotizacion-' . $slug . '.pdf', $pdf);
            }
        }
    }

    public function test_long_quotation_renders_without_database_writes(): void
    {
        $pdf = app(QuotationPdfService::class)->render($this->quotation('San Benjamin', 60))->output();
        $this->assertStringStartsWith('%PDF-', $pdf);
        if ($folder = getenv('QUOTATION_PDF_QA')) {
            file_put_contents($folder . '/cotizacion-multipagina.pdf', $pdf);
        }
    }
}
