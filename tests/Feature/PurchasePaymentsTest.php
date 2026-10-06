<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\PagoCompra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchasePaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function purchase(): Compra
    {
        $user = User::factory()->create([
            'role' => 'administrador',
            'activo' => true,
            'password' => 'Test-Only-123',
        ]);
        $this->actingAs($user);

        return Compra::create([
            'numero_compra' => 'C-PAGO-TEST',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'fecha' => '2026-09-20',
            'subtotal' => 100,
            'total' => 100,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);
    }

    private function payment(float $amount): array
    {
        return [
            'importe' => $amount,
            'medio' => 'transferencia',
            'fecha' => today()->toDateString(),
            'token' => (string) Str::uuid(),
        ];
    }

    public function test_partial_purchase_payments_prevent_duplicates_and_overpayments(): void
    {
        $compra = $this->purchase();
        $data = $this->payment(40);

        $this->post(route('compras.pagos.store', $compra), $data)->assertSessionHasNoErrors();
        $this->post(route('compras.pagos.store', $compra), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('pagos_compras', 1);

        $this->post(route('compras.pagos.store', $compra), $this->payment(61))->assertSessionHasErrors('importe');
        $this->post(route('compras.pagos.store', $compra), $this->payment(60))->assertSessionHasNoErrors();
        $this->assertEquals(100, $compra->pagos()->where('estado', 'registrado')->sum('importe'));
    }

    public function test_purchase_payment_reversal_requires_password_and_reopens_balance(): void
    {
        $compra = $this->purchase();
        $this->post(route('compras.pagos.store', $compra), $this->payment(100));
        $pago = PagoCompra::firstOrFail();

        $this->post(route('pagos-compras.anular', $pago), [
            'force_password' => 'incorrecta',
            'motivo' => 'Referencia errónea',
        ])->assertSessionHasErrors('force_password');
        $this->assertSame('registrado', $pago->fresh()->estado);

        $this->post(route('pagos-compras.anular', $pago), [
            'force_password' => 'Test-Only-123',
            'motivo' => 'Referencia errónea',
        ])->assertSessionHasNoErrors();
        $this->assertSame('anulado', $pago->fresh()->estado);

        $this->post(route('compras.pagos.store', $compra), $this->payment(100))->assertSessionHasNoErrors();
    }

    public function test_purchase_list_shows_pending_partial_and_paid_payment_status(): void
    {
        $pending = $this->purchase();
        $partial = Compra::create($pending->replicate(['numero_compra'])->toArray() + [
            'numero_compra' => 'C-PARCIAL-TEST',
            'usuario_id' => auth()->id(),
        ]);
        $paid = Compra::create($pending->replicate(['numero_compra'])->toArray() + [
            'numero_compra' => 'C-PAGADA-TEST',
            'usuario_id' => auth()->id(),
        ]);
        foreach ([[$partial, 40], [$paid, 100]] as [$compra, $importe]) {
            $compra->pagos()->create($this->payment($importe) + ['usuario_id' => auth()->id()]);
        }

        $this->get(route('compras.index'))
            ->assertOk()
            ->assertSee('Pendiente')
            ->assertSee('Parcial')
            ->assertSee('Pagada');
    }
}
