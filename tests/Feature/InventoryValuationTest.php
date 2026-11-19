<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\TipoCambio;
use App\Models\User;
use App\Models\InventarioLote;
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

    public function test_first_purchase_seeds_missing_product_cost_and_price(): void
    {
        $user = User::factory()->create();
        $product = Producto::create([
            'codigo' => 'NEW-PRICE',
            'nombre' => 'Producto nuevo sin precio',
            'activo' => true,
            'precio_venta' => 0,
            'costo_referencial' => 0,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 0,
        ]);

        app(PurchaseService::class)->registrar([
            'numero_compra' => null,
            'fecha' => '2026-10-26',
            'nombre_comercial' => 'San Benjamin',
            'items' => [[
                'producto_id' => $product->id,
                'cantidad' => 30,
                'costo_unitario' => 143,
                'porcentaje_utilidad' => 27,
            ]],
        ], $user->id);

        $product->refresh();
        $this->assertSame('143.000000', $product->ultimo_costo_compra);
        $this->assertSame('143.00', $product->costo_referencial);
        $this->assertSame('181.61', $product->precio_venta);
        $this->assertSame('bs', $product->moneda_costo);
    }

    public function test_free_partial_purchase_registers_stock_without_erasing_existing_pricing(): void
    {
        $user = User::factory()->create();
        $product = Producto::create([
            'codigo' => 'FREE-GIFT',
            'nombre' => 'Producto recibido como regalo',
            'activo' => true,
            'precio_venta' => 125,
            'costo_referencial' => 100,
            'ultimo_costo_compra' => 100,
            'porcentaje_utilidad' => 25,
            'stock_minimo' => 0,
        ]);

        $purchase = app(PurchaseService::class)->registrar([
            'numero_compra' => null,
            'fecha' => '2026-10-26',
            'nombre_comercial' => 'San Benjamin',
            'items' => [[
                'producto_id' => $product->id,
                'cantidad' => 1,
                'costo_unitario' => 0,
                'porcentaje_utilidad' => 27,
            ]],
        ], $user->id);

        $purchase->load('detalles.lote');
        $product->refresh();
        $detail = $purchase->detalles->first();

        $this->assertSame('0.00', $detail->costo_unitario);
        $this->assertSame('0.0000', $detail->costo_neto_unitario);
        $this->assertSame('0.00', $detail->lote->costo_unitario);
        $this->assertSame('1.00', $product->stock_actual);
        $this->assertSame('100.000000', $product->costo_referencial);
        $this->assertSame('100.000000', $product->ultimo_costo_compra);
        $this->assertSame('125.00', $product->precio_venta);
    }

    public function test_expired_lot_can_be_written_off_without_deleting_history(): void
    {
        $user = User::factory()->create(['name' => 'Operador Inventario']);
        $product = $this->product();
        $purchase = app(PurchaseService::class)->registrar([
            'numero_compra' => null,
            'fecha' => '2026-10-26',
            'nombre_comercial' => 'San Benjamin',
            'items' => [[
                'producto_id' => $product->id,
                'cantidad' => 5,
                'costo_unitario' => 10,
                'codigo_lote' => 'VENCIDO-01',
                'fecha_vencimiento' => '2026-01-01',
            ]],
        ], $user->id);

        $lote = $purchase->detalles->first()->fresh()->lote;

        $this->actingAs($user)
            ->post(route('inventario.lotes.dar-de-baja-vencido', $lote))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('ok');

        $this->assertSame('0.00', $lote->fresh()->cantidad_disponible);
        $this->assertSame('inactivo', $lote->fresh()->estado);
        $this->assertSame(0.0, app(InventoryService::class)->stockActual($product));
        $this->assertDatabaseHas('movimientos_inventario', [
            'producto_id' => $product->id,
            'tipo_movimiento' => 'ajuste_negativo',
            'referencia_tipo' => 'lote_vencido',
            'referencia_id' => $lote->id,
            'cantidad' => 5,
            'usuario_id' => $user->id,
        ]);
        $this->assertDatabaseHas('auditoria_eventos', [
            'modulo' => 'lotes',
            'user_id' => $user->id,
            'auditable_id' => $lote->id,
        ]);
    }

    public function test_non_expired_lot_cannot_be_written_off_as_expired(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $purchase = $this->buy($product, $user, 2, 10);
        $lote = $purchase->detalles->first()->fresh()->lote;

        $this->actingAs($user)
            ->post(route('inventario.lotes.dar-de-baja-vencido', $lote))
            ->assertSessionHasErrors('lote');

        $this->assertSame('2.00', $lote->fresh()->cantidad_disponible);
        $this->assertSame(2.0, $product->fresh()->stock_actual);
    }

    public function test_partial_purchase_can_reduce_negative_stock_without_being_rejected(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $inventory = app(InventoryService::class);

        $inventory->registrarMovimiento($product, 'salida', 10, 'venta', 901, $user->id, 'Salida pendiente', null, true);
        $purchase = $this->buy($product, $user, 1, 15);

        $this->assertSame(-9.0, $inventory->stockActual($product));
        $this->assertSame('1.00', $purchase->detalles->first()->lote->cantidad_inicial);
        $this->assertSame('entrada', $product->movimientos()->latest('id')->first()->tipo_movimiento);
    }

    public function test_purchase_reconciles_documented_shortage_partially_and_completely(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $sale = app(SaleService::class)->registrar([
            'numero_venta' => 'V-PENDING-RECONCILE',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'factura_stock_pendiente' => true,
            'permitir_salida_sin_stock' => true,
            'motivo_salida_sin_stock' => 'Pendiente de ingreso',
            'fecha' => '2026-10-26',
            'items' => [['producto_id' => $product->id, 'cantidad' => 10, 'precio_unitario' => 25]],
        ], $user->id);

        $this->assertSame('10.00', $sale->detalles->first()->faltante_stock);
        $this->buy($product, $user, 4, 15);
        $this->assertSame('6.00', $sale->detalles->first()->fresh()->faltante_stock);

        $this->buy($product, $user, 6, 15, '2026-09-11');
        $this->assertSame('0.00', $sale->detalles->first()->fresh()->faltante_stock);
        $this->assertFalse($sale->fresh()->permitir_salida_sin_stock);
        $this->assertDatabaseMissing('detalle_ventas', [
            'id' => $sale->detalles->first()->id,
            'faltante_stock' => 1,
        ]);
    }

    public function test_zero_quantity_purchase_is_documented_without_stock_or_lot(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        $purchase = app(PurchaseService::class)->registrar([
            'numero_compra' => null,
            'fecha' => '2026-10-26',
            'nombre_comercial' => 'San Benjamin',
            'items' => [[
                'producto_id' => $product->id,
                'cantidad' => 0,
                'costo_unitario' => 0,
                'porcentaje_utilidad' => 27,
            ]],
        ], $user->id);

        $this->assertSame(0.0, app(InventoryService::class)->stockActual($product));
        $this->assertNull($purchase->detalles->first()->fresh()->lote);
        $this->assertSame('0.00', $purchase->detalles->first()->cantidad);
    }

    public function test_regular_sale_still_rejects_insufficient_stock_after_partial_purchase_rule(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $inventory = app(InventoryService::class);

        $inventory->registrarMovimiento($product, 'salida', 2, 'venta', 902, $user->id, 'Salida pendiente', null, true);

        $this->expectException(\InvalidArgumentException::class);
        $inventory->registrarMovimiento($product, 'salida', 1, 'venta', 903, $user->id);
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

    public function test_non_perishable_purchase_can_skip_lot_without_skipping_stock(): void
    {
        $u = User::factory()->create();
        $p = $this->product();

        app(PurchaseService::class)->registrar([
            'numero_compra' => null,
            'fecha' => '2026-10-29',
            'items' => [[
                'producto_id' => $p->id,
                'cantidad' => 10,
                'costo_unitario' => 15,
                'porcentaje_utilidad' => 25,
                'tipo_lote' => 'sin_lote',
                'codigo_lote' => 'NO-DEBE-GUARDARSE',
                'fecha_vencimiento' => '2030-01-01',
            ]],
        ], $u->id);

        $this->assertSame(10.0, app(InventoryService::class)->stockActual($p));
        $this->assertDatabaseMissing('inventario_lotes', ['producto_id' => $p->id]);
    }

    public function test_non_perishable_purchase_can_register_a_lot_without_expiry(): void
    {
        $u = User::factory()->create();
        $p = $this->product();

        app(PurchaseService::class)->registrar([
            'numero_compra' => null,
            'fecha' => '2026-10-29',
            'items' => [[
                'producto_id' => $p->id,
                'cantidad' => 4,
                'costo_unitario' => 15,
                'porcentaje_utilidad' => 25,
                'tipo_lote' => 'sin_vencimiento',
                'codigo_lote' => 'PAPEL-SIN-FECHA',
            ]],
        ], $u->id);

        $this->assertDatabaseHas('inventario_lotes', [
            'producto_id' => $p->id,
            'codigo_lote' => 'PAPEL-SIN-FECHA',
            'fecha_vencimiento' => null,
            'cantidad_disponible' => 4,
        ]);
    }

    public function test_universal_lot_accumulates_non_perishable_purchases(): void
    {
        $u = User::factory()->create();
        $p = $this->product();
        $p->update(['maneja_lote_universal' => true]);

        $this->buy($p, $u, 10, 15);
        $this->buy($p, $u, 7, 17, '2026-10-01');

        $this->assertSame(1, InventarioLote::where('producto_id', $p->id)->where('codigo_lote', 'UNIVERSAL')->count());
        $this->assertDatabaseHas('inventario_lotes', [
            'producto_id' => $p->id,
            'codigo_lote' => 'UNIVERSAL',
            'cantidad_inicial' => 17,
            'cantidad_disponible' => 17,
            'fecha_vencimiento' => null,
        ]);
    }
}
