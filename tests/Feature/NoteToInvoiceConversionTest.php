<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venta;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteToInvoiceConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_delivery_note_converts_to_invoice_without_changing_inventory(): void
    {
        $user = User::factory()->create([
            'role' => 'administrador',
            'activo' => true,
        ]);
        $note = Venta::create([
            'numero_venta' => 'V-CONVERT-001',
            'tipo_documento' => 'nota_entrega',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => today(),
            'estado' => 'registrada',
            'total' => 100,
            'usuario_id' => $user->id,
        ]);

        $this->mock(TwoFactorAuthenticationService::class, function ($mock) {
            $mock->shouldReceive('authorize')->once()->andReturn(null);
        });

        $this->actingAs($user)
            ->post(route('ventas.convertir-factura', $note), [
                'totp_code' => '123456',
                'factura' => 'F-001',
            ])
            ->assertRedirect(route('ventas.show', $note));

        $this->assertDatabaseHas('ventas', [
            'id' => $note->id,
            'tipo_documento' => 'factura',
            'factura' => 'F-001',
        ]);
    }
}
