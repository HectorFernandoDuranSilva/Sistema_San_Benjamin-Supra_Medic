<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryNoteWithoutStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_note_can_record_a_documented_shortage_and_a_later_entry_regularizes_it(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $category = Categoria::create(['nombre' => 'Reactivos', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'SIN-STOCK-01',
            'nombre' => 'Reactivo pendiente',
            'categoria_id' => $category->id,
            'precio_venta' => 25,
            'costo_referencial' => 10,
            'stock_minimo' => 0,
            'activo' => true,
        ]);

        $sale = app(SaleService::class)->registrar([
            'numero_venta' => 'V-NOTA-FALTANTE',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'nota_entrega',
            'fecha' => now()->toDateString(),
            'permitir_salida_sin_stock' => true,
            'motivo_salida_sin_stock' => 'Entrega urgente pendiente de ingreso.',
            'items' => [[
                'producto_id' => $product->id,
                'cantidad' => 3,
                'precio_unitario' => 0,
            ]],
        ], $user->id);

        $this->assertTrue($sale->permitir_salida_sin_stock);
        $this->assertSame('Entrega urgente pendiente de ingreso.', $sale->motivo_salida_sin_stock);
        $this->assertSame(-3.0, app(InventoryService::class)->stockActual($product));

        app(InventoryService::class)->registrarMovimiento($product, 'entrada', 5, 'compra', 1, $user->id, 'Ingreso para regularizar', 12);

        $this->assertSame(2.0, app(InventoryService::class)->stockActual($product));
        $this->assertSame(12.0, (float) $product->fresh()->costo_promedio);
    }

    public function test_delivery_note_without_explicit_exception_records_insufficient_stock_as_pending(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $category = Categoria::create(['nombre' => 'Reactivos', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'SIN-STOCK-02',
            'nombre' => 'Reactivo bloqueado',
            'categoria_id' => $category->id,
            'precio_venta' => 25,
            'costo_referencial' => 10,
            'stock_minimo' => 0,
            'activo' => true,
        ]);

        $sale = app(SaleService::class)->registrar([
            'numero_venta' => 'V-NOTA-BLOQUEADA',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'nota_entrega',
            'fecha' => now()->toDateString(),
            'items' => [[
                'producto_id' => $product->id,
                'cantidad' => 1,
                'precio_unitario' => 0,
            ]],
        ], $user->id);

        $this->assertTrue($sale->permitir_salida_sin_stock);
        $this->assertSame('1.00', $sale->detalles->first()->faltante_stock);
        $this->assertDatabaseHas('ventas', ['numero_venta' => 'V-NOTA-BLOQUEADA']);
    }

    public function test_invoice_can_be_recorded_with_explicit_pending_stock_and_stays_an_invoice(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $category = Categoria::create(['nombre' => 'Reactivos', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'SIN-STOCK-FACTURA',
            'nombre' => 'Reactivo facturado pendiente',
            'categoria_id' => $category->id,
            'precio_venta' => 25,
            'costo_referencial' => 10,
            'stock_minimo' => 0,
            'activo' => true,
        ]);

        $sale = app(SaleService::class)->registrar([
            'numero_venta' => 'V-FACTURA-PENDIENTE',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'factura_stock_pendiente' => true,
            'permitir_salida_sin_stock' => true,
            'motivo_salida_sin_stock' => 'Factura urgente pendiente de ingreso.',
            'fecha' => now()->toDateString(),
            'items' => [[
                'producto_id' => $product->id,
                'cantidad' => 2,
                'precio_unitario' => 25,
            ]],
        ], $user->id);

        $this->assertSame('factura', $sale->tipo_documento);
        $this->assertTrue($sale->permitir_salida_sin_stock);
        $this->assertSame(-2.0, app(InventoryService::class)->stockActual($product));
    }

    public function test_invoice_and_delivery_note_allow_manual_lots_when_stock_is_pending(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $category = Categoria::create(['nombre' => 'Reactivos con lote manual', 'activo' => true]);
        $invoiceProduct = Producto::create([
            'codigo' => 'PEND-MAN-FACT',
            'nombre' => 'Producto manual para factura pendiente',
            'categoria_id' => $category->id,
            'precio_venta' => 25,
            'costo_referencial' => 10,
            'stock_minimo' => 0,
            'activo' => true,
        ]);
        $noteProduct = Producto::create([
            'codigo' => 'PEND-MAN-NOTA',
            'nombre' => 'Producto manual para nota pendiente',
            'categoria_id' => $category->id,
            'precio_venta' => 25,
            'costo_referencial' => 10,
            'stock_minimo' => 0,
            'activo' => true,
        ]);

        $invoice = app(SaleService::class)->registrar([
            'numero_venta' => 'V-PEND-MAN-FACT',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'factura_stock_pendiente' => true,
            'permitir_salida_sin_stock' => true,
            'motivo_salida_sin_stock' => 'Producto por ingresar.',
            'fecha' => now()->toDateString(),
            'items' => [[
                'producto_id' => $invoiceProduct->id,
                'cantidad' => 2,
                'precio_unitario' => 25,
                'codigo_lote_manual' => 'MAN-FACT-01',
            ]],
        ], $user->id);

        $note = app(SaleService::class)->registrar([
            'numero_venta' => 'V-PEND-MAN-NOTA',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'nota_entrega',
            'fecha' => now()->toDateString(),
            'items' => [[
                'producto_id' => $noteProduct->id,
                'cantidad' => 3,
                'precio_unitario' => 0,
                'codigo_lote_manual' => 'MAN-NOTA-01',
            ]],
        ], $user->id);

        $this->assertSame('MAN-FACT-01', $invoice->detalles->first()->codigo_lote_manual);
        $this->assertSame('2.00', $invoice->detalles->first()->faltante_stock);
        $this->assertSame('MAN-NOTA-01', $note->detalles->first()->codigo_lote_manual);
        $this->assertSame('3.00', $note->detalles->first()->faltante_stock);
        $this->assertDatabaseCount('detalle_venta_lotes', 0);
        $this->assertDatabaseHas('inventario_lotes', [
            'producto_id' => $invoiceProduct->id,
            'codigo_lote' => 'MAN-FACT-01',
            'cantidad_inicial' => 0,
            'cantidad_disponible' => 0,
        ]);
        $this->assertDatabaseHas('inventario_lotes', [
            'producto_id' => $noteProduct->id,
            'codigo_lote' => 'MAN-NOTA-01',
            'cantidad_inicial' => 0,
            'cantidad_disponible' => 0,
        ]);
    }

    public function test_pending_document_ignores_a_stale_or_empty_selected_lot(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $category = Categoria::create(['nombre' => 'Reactivos', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'SIN-STOCK-LOTE',
            'nombre' => 'Reactivo con lote agotado',
            'categoria_id' => $category->id,
            'precio_venta' => 25,
            'costo_referencial' => 10,
            'stock_minimo' => 0,
            'activo' => true,
        ]);
        $lot = \App\Models\InventarioLote::create([
            'producto_id' => $product->id,
            'codigo_lote' => 'AGOTADO-01',
            'cantidad_inicial' => 1,
            'cantidad_consumida' => 1,
            'cantidad_disponible' => 0,
            'estado' => 'activo',
        ]);

        $sale = app(SaleService::class)->registrar([
            'numero_venta' => 'V-LOTE-AGOTADO',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'nota_entrega',
            'fecha' => now()->toDateString(),
            'permitir_salida_sin_stock' => true,
            'items' => [[
                'producto_id' => $product->id,
                'cantidad' => 2,
                'precio_unitario' => 0,
                'inventario_lote_id' => $lot->id,
            ]],
        ], $user->id);

        $this->assertSame(2.0, (float) $sale->detalles->first()->faltante_stock);
        $this->assertDatabaseHas('ventas', ['numero_venta' => 'V-LOTE-AGOTADO']);
    }

    public function test_normal_sale_accepts_a_manual_lot_when_stock_exists_without_a_registered_lot(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $category = Categoria::create(['nombre' => 'Producto con lote manual', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'LOTE-MANUAL-NORMAL',
            'nombre' => 'Producto con stock sin lote registrado',
            'categoria_id' => $category->id,
            'precio_venta' => 25,
            'costo_referencial' => 10,
            'stock_minimo' => 0,
            'activo' => true,
        ]);

        app(InventoryService::class)->registrarMovimiento($product, 'entrada', 2, 'ajuste', null, $user->id, 'Stock documentado', 10);
        $sale = app(SaleService::class)->registrar([
            'numero_venta' => 'V-LOTE-MANUAL-NORMAL',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'fecha' => now()->toDateString(),
            'items' => [[
                'producto_id' => $product->id,
                'cantidad' => 1,
                'precio_unitario' => 25,
                'codigo_lote_manual' => 'LOTE-FANTASMA-01',
            ]],
        ], $user->id);

        $this->assertSame('LOTE-FANTASMA-01', $sale->detalles->first()->codigo_lote_manual);
        $this->assertSame(0.0, (float) $sale->detalles->first()->faltante_stock);
        $this->assertSame(1.0, app(InventoryService::class)->stockActual($product));
        $this->assertDatabaseHas('inventario_lotes', [
            'producto_id' => $product->id,
            'codigo_lote' => 'LOTE-FANTASMA-01',
            'cantidad_inicial' => 1,
            'cantidad_disponible' => 0,
            'cantidad_consumida_ajustada' => 1,
        ]);
    }
}
