<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        return [
            'codigo' => 'IDENTITY-1', 'nombre' => 'Reactivo de prueba',
            'marca' => 'Bioclin', 'procedencia' => 'Brasil',
            'moneda_costo' => 'bs', 'costo_ingreso_bs' => 80,
            'tipo_cambio' => 7, 'porcentaje_utilidad' => 25,
            'stock_minimo' => 0, 'activo' => true,
        ];
    }

    public function test_brand_and_origin_are_required_when_creating_products(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrador', 'activo' => true]));
        foreach (['marca', 'procedencia'] as $field) {
            $data = $this->data();
            $data[$field] = '   ';
            $this->post(route('productos.store'), $data)->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('productos', 0);
        $this->post(route('productos.store'), $this->data())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', ['codigo' => 'IDENTITY-1', 'marca' => 'Bioclin', 'procedencia' => 'Brasil']);
    }

    public function test_legacy_products_remain_readable_but_require_identity_when_edited(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrador', 'activo' => true]));
        $product = Producto::create(['codigo' => 'IDENTITY-1', 'nombre' => 'Reactivo antiguo',
            'precio_venta' => 150, 'stock_minimo' => 0, 'activo' => true]);
        $this->get(route('productos.show', $product))->assertOk()->assertSee('Pendiente de completar');
        $this->get(route('productos.edit', $product))->assertOk()->assertSee('name="marca"', false)->assertSee('name="procedencia"', false);
        $data = $this->data();
        unset($data['marca'], $data['procedencia']);
        $this->put(route('productos.update', $product), $data)->assertSessionHasErrors(['marca', 'procedencia']);
        $this->assertNull($product->fresh()->marca);
        $this->put(route('productos.update', $product), $this->data())->assertSessionHasNoErrors();
        $this->assertSame('150.00', $product->fresh()->precio_venta);
        $audit = AuditEvent::where('modulo', 'productos')->latest('id')->firstOrFail();
        $this->assertSame('Bioclin', $product->fresh()->marca);
        $this->assertStringContainsString('Brasil', $audit->toJson());
    }
}
