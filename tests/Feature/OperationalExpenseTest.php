<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\GastoOperativo;
use App\Models\User;
use App\Models\Venta;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OperationalExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_expense_is_registered_outside_purchases_and_inventory(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $this->actingAs($user);

        $this->post(route('reportes.gastos-operativos.store'), [
            'nombre_comercial' => 'Supra Medic',
            'tipo' => 'combustible',
            'modalidad' => 'recojo',
            'documento_tipo' => 'factura',
            'documento_numero' => 'G-101',
            'fecha' => '2026-11-12',
            'importe' => 125.50,
            'descripcion' => 'Combustible para recojo',
        ])->assertRedirect(route('reportes.gastos-operativos', [
            'mes' => '2026-11',
            'nombre_comercial' => 'Supra Medic',
        ]));

        $this->assertDatabaseHas('gastos_operativos', [
            'nombre_comercial' => 'Supra Medic',
            'documento_numero' => 'G-101',
            'importe' => 125.50,
            'usuario_id' => $user->id,
        ]);
        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_monthly_balance_subtracts_operational_expenses_without_affecting_tax_totals(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $this->actingAs($user);

        Venta::create([
            'numero_venta' => 'V-GASTO-001',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'fecha' => '2026-11-12',
            'subtotal' => 1000,
            'total' => 1000,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);
        GastoOperativo::create([
            'nombre_comercial' => 'San Benjamin',
            'tipo' => 'envio',
            'modalidad' => 'envio',
            'documento_tipo' => 'guia',
            'fecha' => '2026-11-12',
            'importe' => 120,
            'usuario_id' => $user->id,
        ]);

        $this->get(route('reportes.balance-mensual', ['mes' => '2026-11', 'nombre_comercial' => 'San Benjamin']))
            ->assertOk()
            ->assertSee('Gastos operativos')
            ->assertSee('Bs 120.00')
            ->assertSee('Bs 880.00')
            ->assertSee('Bs -130.00')
            ->assertSee('Bs 30.00');
    }

    public function test_expense_list_total_covers_all_pages(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $this->actingAs($user);

        foreach (range(1, 31) as $index) {
            GastoOperativo::create([
                'nombre_comercial' => 'San Benjamin',
                'tipo' => 'otro',
                'modalidad' => 'otro',
                'documento_tipo' => 'sin_documento',
                'fecha' => '2026-11-12',
                'importe' => 1,
                'descripcion' => 'Gasto '.$index,
                'usuario_id' => $user->id,
            ]);
        }

        $this->get(route('reportes.gastos-operativos', ['mes' => '2026-11', 'nombre_comercial' => 'San Benjamin']))
            ->assertOk()
            ->assertSee('Bs 31.00');
    }

    public function test_expense_pdf_downloads_a_sales_style_receipt(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $this->actingAs($user);
        $gasto = GastoOperativo::create([
            'nombre_comercial' => 'San Benjamin',
            'tipo' => 'envio',
            'modalidad' => 'envio',
            'documento_tipo' => 'factura',
            'documento_numero' => 'F-1',
            'fecha' => '2026-11-12',
            'importe' => 125,
            'descripcion' => 'Entrega de reactivos',
            'usuario_id' => $user->id,
        ]);

        $this->get(route('reportes.gastos-operativos.pdf', $gasto))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload("gasto-operativo-{$gasto->id}.pdf");
    }

    public function test_purchase_cannot_be_used_as_an_expense_only_record(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Registre combustible, envío, transporte o recojo desde Notas y facturas.');

        app(PurchaseService::class)->registrar([
            'fecha' => '2026-11-12',
            'nombre_comercial' => 'San Benjamin',
        ], $user->id);
    }
}
