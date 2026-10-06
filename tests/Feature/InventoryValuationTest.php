<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\TipoCambio;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ProductPricingService;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryValuationTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Producto
    {
        return Producto::create(['codigo' => 'AVG', 'nombre' => 'Reactivo', 'activo' => true,
            'precio_venta' => 25, 'costo_referencial' => 10, 'porcentaje_utilidad' => 25,
            'precio_ingreso_usd' => 0, 'tipo_cambio' => 7, 'stock_minimo' => 0]);
    }

    private function buy(Producto $p, User $user, float $qty, float $cost, string $date = '2026-09-10')
    {
        return app(PurchaseService::class)->registrar(['numero_compra' => null, 'fecha' => $date,
            'nombre_comercial' => 'San Benjamin', 'factura' => 'FACT-' . $cost,
            'items' => [['producto_id' => $p->id, 'cantidad' => $qty, 'costo_unitario' => $cost,
                'porcentaje_utilidad' => 25, 'codigo_lote' => 'L-' . $cost, 'fecha_vencimiento' => '2028-01-01']]], $user->id);
    }

    public function test_weighted_average_preserves_invoice_prices_and_current_sale_price(): void
    {
        $user = User::factory()->create(); $p = $this->product();
        $first = $this->buy($p, $user, 10, 10);
        $second = $this->buy($p, $user, 20, 15);
        $p->refresh();
        $this->assertSame('13.333333', $p->costo_promedio);
        $this->assertSame('400.000000', $p->valor_inventario);
        $this->assertSame('15.000000', $p->ultimo_costo_compra);
        $this->assertSame('25.00', $p->precio_venta);
        $this->assertSame(16.67, $p->precio_sugerido);
        $this->assertSame('10.00', $first->detalles->first()->fresh()->costo_unitario);
        $this->assertSame('15.00', $second->detalles->first()->costo_unitario);
        $this->assertSame('10.00', $first->detalles->first()->lote->costo_unitario);
    }

    public function test_only_remaining_stock_is_averaged_and_receipts_follow_registration_order(): void
    {
        $u = User::factory()->create(); $p = $this->product();
        $this->buy($p, $u, 10, 10);
        app(InventoryService::class)->registrarMovimiento($p, 'salida', 8, 'venta', 123, $u->id);
        $this->buy($p, $u, 8, 15, '2026-09-01');
        $this->assertSame('14.000000', $p->fresh()->costo_promedio);
        $this->assertSame('140.000000', $p->fresh()->valor_inventario);
        $this->buy($p, $u, 10, 20);
        $this->assertSame('17.000000', $p->fresh()->costo_promedio);
    }

    public function test_cancelled_sale_restores_historical_cost_after_new_purchase(): void
    {
        $u = User::factory()->create(); $p = $this->product(); $inventory = app(InventoryService::class);
        $this->buy($p, $u, 10, 10);
        $inventory->registrarMovimiento($p, 'salida', 5, 'venta', 123, $u->id);
        $this->buy($p, $u, 5, 20);
        $return = $inventory->registrarMovimiento($p, 'anulacion', 5, 'venta', 123, $u->id);
        $this->assertSame('10.000000', $return->costo_valoracion);
        $this->assertSame('200.000000', $p->fresh()->valor_inventario);
        $this->assertSame('13.333333', $p->fresh()->costo_promedio);
    }

    public function test_unknown_opening_cost_is_not_treated_as_free_stock(): void
    {
        $u = User::factory()->create(); $p = $this->product(); $i = app(InventoryService::class);
        $i->registrarMovimiento($p, 'entrada', 10, 'importacion', null, $u->id);
        $this->buy($p, $u, 20, 15);
        $this->assertNull($p->fresh()->costo_promedio);
        $this->assertNull($p->fresh()->precio_sugerido);
        $i->registrarMovimiento($p, 'salida', 30, 'ajuste', null, $u->id);
        $this->buy($p, $u, 2, 17);
        $this->assertSame('17.000000', $p->fresh()->costo_promedio);
        $this->assertSame('34.000000', $p->fresh()->valor_inventario);
    }

    public function test_exchange_rate_changes_only_replacement_reference(): void
    {
        $u = User::factory()->create(); $p = $this->product();
        $p->update(['precio_ingreso_usd' => 2]);
        $this->buy($p, $u, 10, 10);
        app(ProductPricingService::class)->refreshProductsForRate(new TipoCambio(['valor' => 12]));
        $p->refresh();
        $this->assertSame('24.00', $p->costo_referencial);
        $this->assertSame('10.000000', $p->costo_promedio);
        $this->assertSame('25.00', $p->precio_venta);
        $this->assertSame('100.000000', $p->valor_inventario);
    }

    public function test_price_approval_checks_role_and_stale_suggestion_and_records_audit(): void
    {
        $u = User::factory()->create(['role' => 'administrador', 'activo' => true]); $p = $this->product();
        $this->buy($p, $u, 10, 10);
        $this->actingAs($u)->post(route('productos.precio.aprobar', $p), ['base' => 'promedio', 'precio_esperado' => 11])->assertSessionHasErrors();
        $this->assertSame('25.00', $p->fresh()->precio_venta);
        $this->actingAs($u)->post(route('productos.precio.aprobar', $p), ['base' => 'promedio', 'precio_esperado' => 12.5])->assertSessionHasNoErrors();
        $this->assertSame('12.50', $p->fresh()->precio_venta);
        $this->assertDatabaseHas('auditoria_eventos', ['accion' => 'aprobo_precio', 'user_id' => $u->id]);
        $u->update(['role' => 'operador']);
        $this->actingAs($u)->post(route('productos.precio.aprobar', $p), ['base' => 'promedio', 'precio_esperado' => 12.5])->assertForbidden();
    }

    public function test_duplicate_product_rows_are_averaged_in_one_purchase(): void
    {
        $u = User::factory()->create(); $p = $this->product();
        app(PurchaseService::class)->registrar(['numero_compra' => null, 'fecha' => '2026-09-10', 'items' => [
            ['producto_id' => $p->id, 'cantidad' => 2, 'costo_unitario' => 10],
            ['producto_id' => $p->id, 'cantidad' => 2, 'costo_unitario' => 20],
        ]], $u->id);
        $this->assertSame('15.000000', $p->fresh()->costo_promedio);
        $this->assertSame('60.000000', $p->fresh()->valor_inventario);
    }
}
