<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Venta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentsHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function sale(): Venta
    {
        $u = User::factory()->create(['role' => 'administrador', 'activo' => true, 'password' => 'Test-Only-123']);
        $this->actingAs($u);
        return Venta::create(['numero_venta' => 'V-TEST', 'nombre_comercial' => 'Supra Medic',
            'cliente_id' => Cliente::create(['nombre' => 'Cliente prueba', 'documento' => '123', 'activo' => true])->id,
            'fecha' => '2026-08-01', 'tipo_documento' => 'nota_entrega', 'total' => 100,
            'subtotal' => 100, 'estado' => 'registrada', 'usuario_id' => $u->id]);
    }

    private function payment(float $amount): array
    {
        return ['importe' => $amount, 'medio' => 'transferencia', 'fecha' => today()->toDateString(), 'token' => (string) Str::uuid()];
    }

    public function test_partial_payments_duplicate_submission_and_overpayment(): void
    {
        $venta = $this->sale(); $data = $this->payment(40);
        $this->post(route('ventas.cobros.store', $venta), $data)->assertSessionHasNoErrors();
        $this->post(route('ventas.cobros.store', $venta), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cobros', 1);
        $this->post(route('ventas.anular', $venta))->assertSessionHasErrors('venta');
        $this->assertSame('registrada', $venta->fresh()->estado);
        $this->post(route('ventas.cobros.store', $venta), $this->payment(61))->assertSessionHasErrors('importe');
        $this->post(route('ventas.cobros.store', $venta), $this->payment(60))->assertSessionHasNoErrors();
        $this->assertEquals(100, $venta->cobros()->sum('importe'));
        $this->assertDatabaseCount('auditoria_eventos', 2);
    }

    public function test_correction_requires_password_preserves_history_and_reopens_balance(): void
    {
        $venta = $this->sale();
        $this->post(route('ventas.cobros.store', $venta), $this->payment(40));
        $payment = $venta->cobros()->firstOrFail();
        $this->post(route('cobros.anular', $payment), ['force_password' => 'incorrecta', 'motivo' => 'Error'])->assertSessionHasErrors();
        $this->assertSame('registrado', $payment->fresh()->estado);
        $this->post(route('cobros.anular', $payment), ['force_password' => 'Test-Only-123', 'motivo' => 'Referencia incorrecta'])->assertSessionHasNoErrors();
        $this->assertSame('anulado', $payment->fresh()->estado);
        $this->post(route('ventas.cobros.store', $venta), $this->payment(100))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cobros', 2);
    }

    public function test_history_separates_payment_date_and_documents_without_duplicate_on_conversion(): void
    {
        $venta = $this->sale();
        $this->post(route('ventas.cobros.store', $venta), $this->payment(40));
        $this->get(route('clientes.historial', ['desde' => today()->toDateString(), 'hasta' => today()->toDateString()]))
            ->assertOk()->assertViewHas('totalCobrado', fn ($v) => (float) $v === 40.0)
            ->assertViewHas('ventas', fn ($v) => $v->total() === 0);
        $venta->update(['tipo_documento' => 'factura', 'factura' => 'F-123']);
        $this->get(route('clientes.historial', ['cliente_id' => $venta->cliente_id]))->assertOk()
            ->assertViewHas('ventas', fn ($v) => $v->total() === 1)
            ->assertViewHas('totales', fn ($t) => $t->count() === 1 && $t->first()->tipo_documento === 'factura');
        $response = $this->get(route('clientes.historial.pdf', ['cliente_id' => $venta->cliente_id]));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        if ($folder = getenv('HISTORY_PDF_QA')) {
            file_put_contents($folder . '/historico-cliente.pdf', $response->getContent());
        }
    }

    public function test_cancelled_sale_cannot_receive_payment_and_guest_has_no_access(): void
    {
        $venta = $this->sale(); $venta->update(['estado' => 'anulada']);
        $this->post(route('ventas.cobros.store', $venta), $this->payment(40))->assertSessionHasErrors('importe');
        $this->assertDatabaseCount('cobros', 0);
        auth()->logout();
        $this->get(route('clientes.historial'))->assertRedirect(route('login'));
    }

    public function test_complete_purchase_quote_fefo_sale_payment_history_and_reversal_flow(): void
    {
        $u = User::factory()->create(['role' => 'administrador', 'activo' => true, 'password' => 'Test-Only-123']);
        $this->actingAs($u);
        $p = \App\Models\Producto::create(['codigo' => 'FLOW', 'nombre' => 'Reactivo integral', 'activo' => true, 'precio_venta' => 25, 'porcentaje_utilidad' => 25]);
        foreach ([[10, 10, '2028-01-01'], [20, 15, '2029-01-01']] as [$qty, $cost, $expiry]) {
            app(\App\Services\PurchaseService::class)->registrar(['numero_compra' => null, 'fecha' => today()->toDateString(), 'items' => [['producto_id' => $p->id, 'cantidad' => $qty, 'costo_unitario' => $cost, 'porcentaje_utilidad' => 25, 'codigo_lote' => 'LOTE-' . $cost, 'fecha_vencimiento' => $expiry]]], $u->id);
        }
        $data = ['numero_cotizacion' => null, 'fecha' => today()->toDateString(), 'nombre_comercial' => 'Supra Medic', 'items' => [['producto_id' => $p->id, 'cantidad' => 12, 'precio_unitario' => 25, 'porcentaje_utilidad' => 25]]];
        $quotes = app(\App\Services\QuotationService::class);
        $quote = $quotes->registrar($data, $u->id);
        $venta = $quotes->convertirAVenta($quote, $u->id, ['tipo_documento' => 'nota_entrega']);
        $allocations = $venta->detalles->first()->lotes()->with('lote')->get();
        $this->assertEquals(10, $allocations->firstWhere('lote.codigo_lote', 'LOTE-10')->cantidad);
        $this->assertEquals(2, $allocations->firstWhere('lote.codigo_lote', 'LOTE-15')->cantidad);
        $this->assertSame('240.000000', $p->fresh()->valor_inventario);
        $this->assertSame(18.0, app(\App\Services\InventoryService::class)->stockActual($p));
        $this->post(route('ventas.cobros.store', $venta), $this->payment(100))->assertSessionHasNoErrors();
        $this->get(route('clientes.historial'))->assertOk()->assertSee('LOTE-10')->assertSee('Reactivo integral');
        $response = $this->get(route('clientes.historial.pdf'));
        $response->assertOk();
        if ($folder = getenv('HISTORY_PDF_QA')) file_put_contents($folder . '/historico-flujo.pdf', $response->getContent());
        $data['revision'] = 1;
        $this->put(route('cotizaciones.update', $quote), $data)->assertSessionHasErrors();
        $this->post(route('ventas.anular', $venta))->assertSessionHasErrors('venta');
        $payment = $venta->cobros()->first();
        $this->post(route('cobros.anular', $payment), ['force_password' => 'Test-Only-123', 'motivo' => 'Prueba de reversión'])->assertSessionHasNoErrors();
        $this->post(route('ventas.anular', $venta))->assertSessionHasNoErrors();
        $this->assertSame(30.0, app(\App\Services\InventoryService::class)->stockActual($p));
        $this->assertSame('400.000000', $p->fresh()->valor_inventario);
        $this->post(route('ventas.anular', $venta))->assertSessionHasNoErrors();
        $this->assertSame(30.0, app(\App\Services\InventoryService::class)->stockActual($p));
    }
}
