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

    public function test_monthly_balance_exports_separate_company_totals_to_excel(): void
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
        $this->assertSame('=D6-B6', $resumen->getCell('F6')->getValue());
        $this->assertSame('=F6*13%', $resumen->getCell('G6')->getValue());
        $this->assertSame('=G6+H6', $resumen->getCell('I6')->getValue());
        $this->assertSame('=SUM(B6:B7)', $resumen->getCell('B8')->getValue());
        $this->assertNull($spreadsheet->getSheetByName('Facturas venta')->getProtection()->getSheet());
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
