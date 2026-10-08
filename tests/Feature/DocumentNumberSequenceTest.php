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

    public function test_new_document_sequences_ignore_imported_history_and_continue_from_the_last_native_number(): void
    {
        $user = User::factory()->create();

        Venta::create([
            'numero_venta' => 'EXCEL-V-955', 'fecha' => '2026-10-01', 'subtotal' => 1, 'total' => 1,
            'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);
        Venta::create([
            'numero_venta' => 'V-000945', 'fecha' => '2026-10-02', 'subtotal' => 1, 'total' => 1,
            'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);
        Compra::create([
            'numero_compra' => 'EXCEL-C-391', 'fecha' => '2026-10-01', 'subtotal' => 1, 'total' => 1,
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

        $this->assertSame('V-000946', $this->nextNumber(SaleService::class));
        $this->assertSame('C-000389', $this->nextNumber(PurchaseService::class));
        $this->assertSame('CT-000017', $this->nextNumber(QuotationService::class));
    }

    private function nextNumber(string $service): string
    {
        $method = new \ReflectionMethod($service, 'siguienteNumero');

        return $method->invoke(app($service));
    }
}
