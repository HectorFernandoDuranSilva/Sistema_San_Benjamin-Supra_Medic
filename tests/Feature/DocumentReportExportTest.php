<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DocumentReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_report_exports_the_four_separated_groups_to_excel(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $this->actingAs($user);

        foreach ([['V-FAC', 'factura'], ['V-NOTA', 'nota_entrega']] as [$number, $type]) {
            Venta::create([
                'numero_venta' => $number,
                'nombre_comercial' => 'San Benjamin',
                'tipo_documento' => $type,
                'fecha' => '2026-09-20',
                'subtotal' => 100,
                'total' => 100,
                'estado' => 'registrada',
                'usuario_id' => $user->id,
            ]);
        }
        foreach ([['C-FAC', 'factura'], ['C-NOTA', 'nota_entrega']] as [$number, $type]) {
            Compra::create([
                'numero_compra' => $number,
                'nombre_comercial' => 'Supra Medic',
                'tipo_documento' => $type,
                'fecha' => '2026-09-20',
                'subtotal' => 100,
                'total' => 100,
                'estado' => 'registrada',
                'usuario_id' => $user->id,
            ]);
        }

        $periodo = ['desde' => '2026-09-01', 'hasta' => '2026-09-30'];

        $this->get(route('reportes.documentos', $periodo))
            ->assertOk()
            ->assertSee('Facturas de compra')
            ->assertSee('Notas de compra')
            ->assertSee('V-FAC')
            ->assertSee('V-NOTA')
            ->assertSee('C-FAC')
            ->assertSee('C-NOTA');

        $this->get(route('reportes.documentos', ['buscar' => 'V-NOTA']))
            ->assertOk()
            ->assertSee('V-NOTA')
            ->assertDontSee('V-FAC')
            ->assertDontSee('C-NOTA');

        $response = $this->get(route('reportes.documentos.excel', $periodo));
        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', $content);

        $spreadsheet = $this->loadSpreadsheet($content);
        $resumen = $spreadsheet->getSheetByName('Resumen');
        $this->assertSame('=SUM(\'Facturas venta\'!$G$2:$G$2)', $resumen->getCell('B6')->getValue());
        $this->assertSame('=B8-B6', $resumen->getCell('B10')->getValue());
        $this->assertSame('=B10*13%', $resumen->getCell('B11')->getValue());
        $this->assertSame('=B11+B12', $resumen->getCell('B13')->getValue());
        $this->assertSame('Edite esta copia solo para revisión contable. Los cambios no modifican los documentos del sistema.', $resumen->getCell('B3')->getValue());
    }

    public function test_monthly_balance_excludes_delivery_notes_from_financial_totals_in_excel(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $this->actingAs($user);
        Venta::create([
            'numero_venta' => 'V-BALANCE-EXCEL',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'fecha' => '2026-09-20',
            'subtotal' => 100,
            'total' => 100,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);
        Compra::create([
            'numero_compra' => 'C-BALANCE-EXCEL',
            'nombre_comercial' => 'Supra Medic',
            'tipo_documento' => 'nota_entrega',
            'fecha' => '2026-09-20',
            'subtotal' => 50,
            'total' => 50,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);

        $response = $this->get(route('reportes.balance-mensual.excel', ['mes' => '2026-09']));
        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', $content);

        $spreadsheet = $this->loadSpreadsheet($content);
        $resumen = $spreadsheet->getSheetByName('Resumen mensual');
        $this->assertSame('=SUMIF(\'Facturas venta\'!$C$2:$C$2,$A6,\'Facturas venta\'!$G$2:$G$2)', $resumen->getCell('B6')->getValue());
        $this->assertSame('=C6-B6', $resumen->getCell('D6')->getValue());
        $this->assertSame('=D6*13%', $resumen->getCell('E6')->getValue());
        $this->assertSame('=E6+F6', $resumen->getCell('G6')->getValue());
        $this->assertSame('=SUM(B6:B7)', $resumen->getCell('B8')->getValue());
        $this->assertNull($spreadsheet->getSheetByName('Notas venta'));
        $this->assertNull($spreadsheet->getSheetByName('Notas compra'));
        $this->assertNull($spreadsheet->getSheetByName('Facturas venta')->getProtection()->getSheet());
    }

    public function test_delivery_notes_have_a_separate_monthly_total_and_pdf(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $this->actingAs($user);

        Venta::create([
            'numero_venta' => 'V-FACTURA-BAL',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'fecha' => '2026-09-15',
            'subtotal' => 200,
            'total' => 200,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);
        Venta::create([
            'numero_venta' => 'V-NOTA-MES',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'nota_entrega',
            'fecha' => '2026-09-16',
            'subtotal' => 75,
            'total' => 75,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);
        Venta::create([
            'numero_venta' => 'V-NOTA-OTRO-MES',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'nota_entrega',
            'fecha' => '2026-10-01',
            'subtotal' => 20,
            'total' => 20,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);
        Venta::create([
            'numero_venta' => 'V-NOTA-ANULADA',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'nota_entrega',
            'fecha' => '2026-09-17',
            'subtotal' => 30,
            'total' => 30,
            'estado' => 'anulada',
            'usuario_id' => $user->id,
        ]);

        $this->get(route('reportes.balance-mensual', ['mes' => '2026-09', 'nombre_comercial' => 'San Benjamin']))
            ->assertOk()
            ->assertSee('Ventas facturadas')
            ->assertSee('Bs 200.00')
            ->assertSee('Notas de entrega')
            ->assertSee('Bs 75.00');

        $this->get(route('reportes.notas-entrega.pdf', ['mes' => '2026-09', 'nombre_comercial' => 'San Benjamin']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('notas-entrega-2026-09.pdf');
    }

    private function loadSpreadsheet(string $content)
    {
        $path = tempnam(sys_get_temp_dir(), 'reporte-excel-');
        file_put_contents($path, $content);

        try {
            return IOFactory::load($path);
        } finally {
            @unlink($path);
        }
    }
}
