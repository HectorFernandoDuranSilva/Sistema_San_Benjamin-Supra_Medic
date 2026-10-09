<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
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

    public function test_delivery_note_without_explicit_exception_still_rejects_insufficient_stock(): void
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

        try {
            app(SaleService::class)->registrar([
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
            $this->fail('La nota sin autorización no debió registrarse.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Stock vigente insuficiente', $exception->getMessage());
        }

        $this->assertDatabaseMissing('ventas', ['numero_venta' => 'V-NOTA-BLOQUEADA']);
    }
}
