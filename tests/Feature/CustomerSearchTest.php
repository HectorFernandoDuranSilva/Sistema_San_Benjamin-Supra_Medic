<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_can_be_found_by_name_business_name_or_nit(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $cliente = Cliente::create([
            'nombre' => 'GOBIERNO AUTONOMO MUNICIPAL DE CULPINA',
            'razon_social' => 'CULPINA',
            'documento' => '120147027',
            'activo' => true,
        ]);

        foreach (['CULPINA', '120147027', 'MUNICIPAL DE CULPINA', 'GOBIERNO AUTONOMO MUNICIPAL', 'AUTONOMO'] as $buscar) {
            $this->actingAs($user)
                ->get(route('clientes.index', ['buscar' => $buscar]))
                ->assertOk()
                ->assertSee($cliente->nombre);
        }
    }

    public function test_sales_form_includes_institute_and_institution_clients(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        Cliente::create([
            'nombre' => 'INSTITUTO MARIANO',
            'razon_social' => 'MONTEAGUDO',
            'documento' => '121971026',
            'activo' => true,
        ]);
        Cliente::create([
            'nombre' => 'BANCO DE SANGRE USFX',
            'razon_social' => 'INSTITUCION',
            'documento' => '11111',
            'activo' => true,
        ]);

        $this->actingAs($user)
            ->get(route('ventas.create'))
            ->assertOk()
            ->assertSee('INSTITUTO MARIANO - MONTEAGUDO')
            ->assertSee('BANCO DE SANGRE USFX (Institución)');
    }
}
