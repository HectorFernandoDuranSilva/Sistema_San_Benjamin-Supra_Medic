<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaCuota;
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleInstallmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_creates_an_exact_installment_schedule(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $product = Producto::create(['codigo' => 'CUOTA-001', 'nombre' => 'Producto a cuotas', 'precio_venta' => 100, 'stock_minimo' => 0, 'activo' => true]);
        app(InventoryService::class)->registrarMovimiento($product, 'entrada', 5, 'producto', $product->id, $user->id);

        $sale = app(SaleService::class)->registrar([
            'numero_venta' => 'V-CUOTAS', 'nombre_comercial' => 'San Benjamin', 'fecha' => '2026-10-04',
            'cliente_id' => Cliente::create(['nombre' => 'Cliente cuotas', 'activo' => true])->id,
            'plan_pago' => 'cuotas',
            'cuotas' => [
                ['fecha_vencimiento' => '2026-11-04', 'importe' => 45, 'observacion' => 'Primera cuota'],
                ['fecha_vencimiento' => '2026-12-04', 'importe' => 55],
            ],
            'items' => [['producto_id' => $product->id, 'cantidad' => 1, 'precio_unitario' => 100]],
        ], $user->id);

        $this->assertSame('100.00', $sale->total);
        $this->assertCount(2, $sale->cuotas);
        $this->assertSame('45.00', $sale->cuotas->first()->importe);
        $this->assertSame('2026-11-04', $sale->cuotas->first()->fecha_vencimiento->toDateString());
    }

    public function test_payment_is_assigned_to_a_pending_installment_without_exceeding_it(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $this->actingAs($user);
        $sale = Venta::create([
            'numero_venta' => 'V-CUOTA-COBRO', 'nombre_comercial' => 'Supra Medic', 'fecha' => '2026-10-04',
            'tipo_documento' => 'factura', 'subtotal' => 100, 'total' => 100, 'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);
        $first = VentaCuota::create(['venta_id' => $sale->id, 'numero' => 1, 'fecha_vencimiento' => '2026-10-14', 'importe' => 40]);
        $second = VentaCuota::create(['venta_id' => $sale->id, 'numero' => 2, 'fecha_vencimiento' => '2026-11-14', 'importe' => 60]);

        $this->post(route('ventas.cobros.store', $sale), $this->payment(25, $first))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cobros', ['venta_id' => $sale->id, 'cuota_id' => $first->id, 'importe' => 25]);
        $this->post(route('ventas.cobros.store', $sale), $this->payment(16, $first))->assertSessionHasErrors('importe');
        $this->post(route('ventas.cobros.store', $sale), $this->payment(15, $first))->assertSessionHasNoErrors();
        $this->post(route('ventas.cobros.store', $sale), $this->payment(1, null))->assertSessionHasErrors('cuota_id');
        $this->post(route('ventas.cobros.store', $sale), $this->payment(60, $second))->assertSessionHasNoErrors();
    }

    private function payment(float $amount, ?VentaCuota $installment): array
    {
        return [
            'token' => (string) Str::uuid(), 'fecha' => '2026-10-04', 'importe' => $amount,
            'medio' => 'transferencia', 'cuota_id' => $installment?->id,
        ];
    }
}
