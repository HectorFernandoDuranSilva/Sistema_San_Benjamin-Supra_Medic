<?php

namespace Tests\Feature;

use App\Models\{Cliente, ConfiguracionEmpresa, DetalleVenta, DetalleVentaLote, InventarioLote, Producto, Venta};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class SaleReceiptLotsTest extends TestCase
{
    private function data(int $items, int $lots, string $brand = 'San Benjamin'): array
    {
        $details = new Collection;
        for ($i = 1; $i <= $items; $i++) {
            $detail = new DetalleVenta(['codigo_producto' => 'P-' . $i,
                'nombre_producto' => 'Reactivo de laboratorio ' . $i, 'cantidad' => max(1, $lots),
                'precio_unitario' => 25, 'subtotal' => max(1, $lots) * 25, 'total' => max(1, $lots) * 25]);
            $detail->setRelation('producto', new Producto(['medida' => 'UNIDAD']));
            $allocations = new Collection;
            for ($j = 1; $j <= ($i === 1 ? $lots : 0); $j++) {
                $allocation = new DetalleVentaLote(['cantidad' => 1,
                    'fecha_vencimiento' => $j === 2 ? null : '2028-10-15']);
                $allocation->setRelation('lote', new InventarioLote(['codigo_lote' => 'LOTE-' . $j]));
                $allocations->push($allocation);
            }
            $detail->setRelation('lotes', $allocations);
            $details->push($detail);
        }
        $venta = new Venta(['numero_venta' => 'V-PRUEBA', 'fecha' => '2026-09-10',
            'nombre_comercial' => $brand, 'factura' => '123', 'tipo_documento' => 'nota_entrega',
            'subtotal' => 300, 'total' => 300]);
        $venta->setRelation('cliente', new Cliente(['nombre' => 'Cliente de prueba', 'documento' => '123456',
            'direccion' => 'Sucre', 'telefono' => '12345678']));
        $venta->setRelation('detalles', $details);
        return ['venta' => $venta, 'empresa' => new ConfiguracionEmpresa(['direccion' => 'Calle Grau No 661']), 'isPdf' => true];
    }

    public function test_receipts_show_assigned_lots_for_both_brands_and_document_types(): void
    {
        foreach (['San Benjamin', 'Supra Medic'] as $brand) {
            foreach (['nota_entrega', 'factura'] as $type) {
                $data = $this->data(2, 3, $brand);
                $data['venta']->tipo_documento = $type;
                $html = view('recibos.venta', $data)->render();
                foreach (['LOTES Y FECHAS DE VENCIMIENTO', 'LOTE-3', '15/10/2028', 'Sin fecha', 'Firma', 'Nombre'] as $text) {
                    $this->assertStringContainsString($text, $html);
                }
                $this->assertStringNotContainsString('Sitio Web', $html);
                $this->assertStringContainsString($brand === 'Supra Medic' ? '#31d1c9' : '#86bc25', $html);
                $this->assertStringContainsString($brand === 'Supra Medic' ? 'supra-medic.jpeg' : 'san-benjamin.jpeg', $html);
                $pdf = Pdf::loadView('recibos.venta', $data)->setPaper('letter')->output();
                $this->assertStringStartsWith('%PDF-', $pdf);
                if ($folder = getenv('RECEIPT_PDF_QA')) {
                    file_put_contents($folder . '/' . ($brand === 'Supra Medic' ? 'supra' : 'san-benjamin') . '-' . $type . '.pdf', $pdf);
                }
            }
        }
    }

    public function test_receipts_do_not_truncate_more_than_nine_lots_or_eighteen_products(): void
    {
        $data = $this->data(40, 12);
        $html = view('recibos.venta', $data)->render();
        $this->assertStringContainsString('LOTE-12', $html);
        $this->assertStringContainsString('P-40', $html);
        $this->assertStringContainsString('(continuación)', $html);
        $pdf = Pdf::loadView('recibos.venta', $data)->setPaper('letter')->output();
        $this->assertStringStartsWith('%PDF-', $pdf);
        if ($folder = getenv('RECEIPT_PDF_QA')) {
            file_put_contents($folder . '/muchos-lotes.pdf', $pdf);
        }
    }

    public function test_receipts_without_lots_keep_the_original_body(): void
    {
        $html = view('recibos.venta', $this->data(1, 0))->render();
        $this->assertStringNotContainsString('LOTES Y FECHAS DE VENCIMIENTO', $html);
        $this->assertStringContainsString('Costo envío', $html);
        $this->assertStringContainsString('Firma', $html);
    }
}
