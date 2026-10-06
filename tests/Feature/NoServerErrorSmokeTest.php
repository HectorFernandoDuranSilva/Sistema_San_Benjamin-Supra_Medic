<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoServerErrorSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_primary_modules_open_without_server_errors(): void
    {
        $user = User::factory()->create([
            'role' => 'administrador',
            'activo' => true,
        ]);

        $session = [
            'commercial_context' => [
                'key' => 'san-benjamin',
                'name' => 'San Benjamin',
                'nit' => '411168020',
                'logo' => 'assets/logos/san-benjamin.png',
            ],
        ];

        $routes = [
            'dashboard',
            'productos',
            'productos/create',
            'categorias',
            'clientes',
            'proveedores',
            'inventario',
            'inventario/movimientos',
            'inventario/lotes',
            'ventas',
            'ventas/create',
            'notas-facturas/notas/crear',
            'compras',
            'compras/create',
            'cotizaciones',
            'cotizaciones/create',
            'historico-clientes',
            'reportes/ventas',
            'reportes/documentos',
            'reportes/balance-mensual',
            'reportes/guardados',
            'reportes/inventario',
            'reportes/kardex',
            'reportes/compras',
            'configuracion',
            'usuarios',
            'importar/excel',
        ];

        foreach ($routes as $route) {
            $this->actingAs($user)
                ->withSession($session)
                ->get('/' . $route)
                ->assertOk();
        }
    }
}

