<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationEditingTest extends TestCase
{
    use RefreshDatabase;

    private function data(Producto $p): array
    {
        return ['numero_cotizacion' => null, 'fecha' => '2026-09-10', 'nombre_comercial' => 'Supra Medic',
            'footer_direccion' => 'Grau # 661: 464-39322 67632137',
            'footer_validez' => '7 días calendario',
            'footer_entrega' => '24 horas, dependiendo al ítem y el stock en almacén.',
            'footer_condiciones_pago' => 'Transferencia bancaria',
            'footer_deposito_cuenta' => 'Banco Unión 1-6427012 Distribuidora Supra Medic',
            'items' => [['producto_id' => $p->id, 'cantidad' => 2, 'precio_unitario' => 20,
                'porcentaje_utilidad' => 25, 'descuento' => 3, 'impuesto' => 0]]];
    }

    public function test_edit_add_remove_and_audit_preserve_number_and_do_not_move_stock(): void
    {
        $u = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $p = Producto::create(['codigo' => 'QUOTE', 'nombre' => 'Reactivo', 'activo' => true, 'costo_referencial' => 16, 'precio_venta' => 20]);
        $service = app(QuotationService::class); $data = $this->data($p);
        $quote = $service->registrar($data, $u->id);
        $this->assertSame('7 días calendario', $quote->footer_validez);
        $this->assertSame('Banco Unión 1-6427012 Distribuidora Supra Medic', $quote->footer_deposito_cuenta);
        $this->actingAs($u)->get(route('cotizaciones.edit', $quote))->assertOk()->assertSee('Guardar cambios')->assertSee('initialItems', false);
        $data['revision'] = 1;
        $data['items'][0]['id'] = $quote->detalles->first()->id;
        $data['items'][0]['cantidad'] = 3;
        $data['items'][] = ['producto_id' => $p->id, 'cantidad' => 1, 'precio_unitario' => 22, 'porcentaje_utilidad' => 25];
        $this->actingAs($u)->put(route('cotizaciones.update', $quote), $data)->assertSessionHasNoErrors();
        $quote->refresh();
        $this->assertSame('79.00', $quote->total);
        $this->assertSame(2, (int) $quote->revision);
        $this->assertSame(2, $quote->detalles()->count());
        $this->assertDatabaseHas('auditoria_eventos', ['modulo' => 'cotizaciones', 'accion' => 'actualizo']);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('ventas', 0);
        $number = $quote->numero_cotizacion;
        $data['revision'] = 2;
        $data['items'] = [$quote->detalles()->first()->only(['id', 'producto_id', 'cantidad', 'precio_unitario', 'porcentaje_utilidad', 'descuento', 'impuesto'])];
        $this->actingAs($u)->put(route('cotizaciones.update', $quote), $data)->assertSessionHasNoErrors();
        $this->assertSame('57.00', $quote->fresh()->total);
        $this->assertSame($number, $quote->fresh()->numero_cotizacion);
        $this->assertSame(1, $quote->detalles()->count());
    }

    public function test_stale_revision_and_converted_quote_are_rejected_without_changes(): void
    {
        $u = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $p = Producto::create(['codigo' => 'Q2', 'nombre' => 'Reactivo', 'activo' => true]);
        $data = $this->data($p); $quote = app(QuotationService::class)->registrar($data, $u->id);
        $data['revision'] = 2;
        $this->actingAs($u)->put(route('cotizaciones.update', $quote), $data)->assertSessionHasErrors('cotizacion');
        $this->assertSame('37.00', $quote->fresh()->total);
        $quote->update(['estado' => 'convertida']); $data['revision'] = 1;
        $this->actingAs($u)->put(route('cotizaciones.update', $quote), $data)->assertSessionHasErrors('cotizacion');
        $this->assertDatabaseCount('auditoria_eventos', 0);
        $this->assertSame(1, (int) $quote->fresh()->revision);
    }

    public function test_identity_is_copied_and_manual_values_survive_catalog_changes(): void
    {
        $u = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $p = Producto::create(['codigo' => 'META', 'nombre' => 'Reactivo', 'marca' => 'Bioclin', 'procedencia' => 'Brasil', 'activo' => true]);
        $lot = $p->lotes()->create(['cantidad_inicial' => 10, 'cantidad_disponible' => 10, 'fecha_vencimiento' => '2027-05-31', 'estado' => 'activo']);
        $data = $this->data($p);
        $data['items'][0]['inventario_lote_id'] = $lot->id;
        $service = app(QuotationService::class);
        $quote = $service->registrar($data, $u->id);
        $detail = $quote->detalles->first();
        $this->assertSame('Bioclin', $detail->marca);
        $this->assertSame('2027-05-31', $detail->fecha_vencimiento->toDateString());
        $p->update(['marca' => 'Otra marca']);
        $data['revision'] = 1;
        $data['items'][0] += ['id' => $detail->id, 'procedencia' => 'Origen manual', 'fecha_vencimiento' => '2028-01-31'];
        $this->actingAs($u)->put(route('cotizaciones.update', $quote), $data)->assertSessionHasNoErrors();
        $updated = $quote->fresh()->detalles->first();
        $this->assertSame('Bioclin', $updated->marca);
        $this->assertSame('Origen manual', $updated->procedencia);
        $this->assertSame('2028-01-31', $updated->fecha_vencimiento->toDateString());
        $this->assertSame('Brasil', $p->fresh()->procedencia);
        $this->assertSame('2027-05-31', $lot->fresh()->fecha_vencimiento->toDateString());
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('detalle_venta_lotes', 0);
    }

    public function test_quotation_rejects_a_lot_from_a_different_product(): void
    {
        $u = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $p = Producto::create(['codigo' => 'Q-META', 'nombre' => 'Reactivo', 'activo' => true]);
        $other = Producto::create(['codigo' => 'OTHER', 'nombre' => 'Otro', 'activo' => true]);
        $lot = $other->lotes()->create(['cantidad_inicial' => 1, 'cantidad_disponible' => 1]);
        $data = $this->data($p);
        $data['items'][0]['inventario_lote_id'] = $lot->id;
        $this->actingAs($u)->post(route('cotizaciones.store'), $data)->assertSessionHasErrors('cotizacion');
        $this->assertDatabaseCount('cotizaciones', 0);
    }

    public function test_selected_commercial_name_is_saved_even_when_dashboard_context_is_different(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $product = Producto::create(['codigo' => 'Q-CONTEXT', 'nombre' => 'Reactivo', 'activo' => true]);
        $data = $this->data($product);

        $this->withSession(['commercial_context' => ['name' => 'San Benjamin']])
            ->actingAs($user)
            ->post(route('cotizaciones.store'), $data)
            ->assertSessionHasNoErrors();

        $quote = \App\Models\Cotizacion::firstOrFail();
        $this->assertSame('Supra Medic', $quote->nombre_comercial);

        $data['nombre_comercial'] = 'San Benjamin';
        $data['revision'] = 1;
        $data['items'][0]['id'] = $quote->detalles()->value('id');
        $this->withSession(['commercial_context' => ['name' => 'Supra Medic']])
            ->actingAs($user)
            ->put(route('cotizaciones.update', $quote), $data)
            ->assertSessionHasNoErrors();

        $this->assertSame('San Benjamin', $quote->fresh()->nombre_comercial);
    }
}
