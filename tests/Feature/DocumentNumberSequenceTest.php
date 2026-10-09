<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Cotizacion;
use App\Models\User;
use App\Models\Venta;
use App\Services\PurchaseService;
use App\Services\QuotationService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNumberSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_document_sequences_continue_from_the_highest_imported_or_native_number(): void
    {
        $user = User::factory()->create();

        Venta::create([
            'numero_venta' => 'EXCEL-V-990', 'fecha' => '2026-10-01', 'subtotal' => 1, 'total' => 1,
            'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);
        Venta::create([
            'numero_venta' => 'V-000945', 'fecha' => '2026-10-02', 'subtotal' => 1, 'total' => 1,
            'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);
        foreach (['981', '982', '983'] as $number) {
            Venta::create([
                'numero_venta' => "EXCEL-V-{$number}", 'fecha' => '2026-10-02', 'subtotal' => 1, 'total' => 1,
                'estado' => 'registrada', 'usuario_id' => $user->id,
            ]);
        }
        Compra::create([
            'numero_compra' => 'EXCEL-C-394', 'fecha' => '2026-10-01', 'subtotal' => 1, 'total' => 1,
            'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);
        Compra::create([
            'numero_compra' => 'C-000388', 'fecha' => '2026-10-02', 'subtotal' => 1, 'total' => 1,
            'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);
        Cotizacion::create([
            'numero_cotizacion' => 'EXCEL-CT-120', 'fecha' => '2026-10-01', 'subtotal' => 1, 'total' => 1,
            'estado' => 'emitida', 'usuario_id' => $user->id,
        ]);
        Cotizacion::create([
            'numero_cotizacion' => 'CT-000016', 'fecha' => '2026-10-02', 'subtotal' => 1, 'total' => 1,
            'estado' => 'emitida', 'usuario_id' => $user->id,
        ]);

        $this->assertSame('V-000991', $this->nextNumber(SaleService::class));
        $this->assertSame('C-000395', $this->nextNumber(PurchaseService::class));
        $this->assertSame('CT-000121', $this->nextNumber(QuotationService::class));
        $this->assertSame(['EXCEL-V-983', 'EXCEL-V-982', 'EXCEL-V-981'], Venta::query()
            ->whereIn('numero_venta', ['EXCEL-V-981', 'EXCEL-V-982', 'EXCEL-V-983'])
            ->orderByRaw("CASE WHEN numero_venta LIKE 'EXCEL-V-%' THEN CAST(SUBSTR(numero_venta, 9) AS INTEGER) ELSE id END DESC")
            ->pluck('numero_venta')
            ->all());
    }

    private function nextNumber(string $service): string
    {
        $method = new \ReflectionMethod($service, 'siguienteNumero');

        return $method->invoke(app($service));
    }
}
