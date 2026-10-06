<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\Producto;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\QuotationPdfService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationOrderItemsTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        return ['numero_cotizacion' => null, 'fecha' => '2026-10-04', 'nombre_comercial' => 'Supra Medic',
            'items' => [['por_pedido' => true, 'nombre_producto' => 'Reactivo por encargo', 'medida' => 'CAJA X 20',
                'marca' => 'Marca ofrecida', 'procedencia' => 'Brasil', 'fecha_vencimiento' => '2028-05-31',
                'cantidad' => 2, 'precio_unitario' => 100, 'porcentaje_utilidad' => 27]]];
    }

    public function test_order_items_are_saved_edited_and_printed_without_creating_inventory(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $this->actingAs($user)->post(route('cotizaciones.store'), $this->data())->assertSessionHasNoErrors();
        $quote = Cotizacion::firstOrFail();
        $this->assertNull($quote->detalles->first()->producto_id);
        $this->assertSame('200.00', $quote->total);
        $this->assertDatabaseCount('productos', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('inventario_lotes', 0);
        $this->get(route('cotizaciones.edit', $quote))->assertOk()->assertSee('Reactivo por encargo');
        $data = $this->data();
        $data['revision'] = 1;
        $data['items'][0]['id'] = $quote->detalles->first()->id;
        $data['items'][0]['cantidad'] = 3;
        $this->put(route('cotizaciones.update', $quote), $data)->assertSessionHasNoErrors();
        $quote = $quote->fresh(['detalles.producto', 'cliente']);
        $this->assertSame('300.00', $quote->total);
        $html = view('cotizaciones.pdf', app(QuotationPdfService::class)->data($quote))->render();
        foreach (['Reactivo por encargo', 'CAJA X 20', 'Marca ofrecida', '31/05/2028'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $quote->update(['confirmada_at' => now(), 'confirmada_por_id' => $user->id]);
        $this->get(route('cotizaciones.pdf', $quote))->assertOk();
        $this->assertDatabaseCount('productos', 0);
    }

    public function test_conversion_requires_linking_and_then_uses_normal_stock_flow(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $service = app(QuotationService::class);
        $quote = $service->registrar($this->data(), $user->id);
        $this->actingAs($user)->post(route('cotizaciones.convertir', $quote))->assertSessionHasErrors('cotizacion');
        $this->assertDatabaseCount('ventas', 0);
        $this->assertNull($quote->fresh()->venta_id);
        $p = Producto::create(['codigo' => 'ENCARGO', 'nombre' => 'Reactivo recibido', 'medida' => 'CAJA X 20', 'activo' => true]);
        app(InventoryService::class)->registrarMovimiento($p, 'entrada', 5, 'producto', $p->id, $user->id);
        $data = $this->data();
        $data['revision'] = 1;
        $data['items'][0] += ['id' => $quote->detalles->first()->id, 'producto_id' => $p->id];
        $this->put(route('cotizaciones.update', $quote), $data)->assertSessionHasNoErrors();
        $this->assertSame($p->id, $quote->fresh()->detalles->first()->producto_id);
        $sale = $service->convertirAVenta($quote->fresh(), $user->id);
        $this->assertSame('200.00', $sale->total);
        $this->assertSame(3.0, $p->fresh()->stock_actual);
    }

    public function test_empty_and_disguised_catalog_items_are_rejected(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $data = $this->data();
        $data['items'][0]['nombre_producto'] = '   ';
        $this->actingAs($user)->post(route('cotizaciones.store'), $data)->assertSessionHasErrors('cotizacion');
        $data = $this->data();
        $data['items'][0]['por_pedido'] = false;
        $this->post(route('cotizaciones.store'), $data)->assertSessionHasErrors('cotizacion');
        $this->assertDatabaseCount('cotizaciones', 0);
    }
}
