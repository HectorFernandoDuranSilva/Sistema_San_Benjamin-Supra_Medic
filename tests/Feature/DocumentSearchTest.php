<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Cobro;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSearchTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $this->actingAs($user);

        return $user;
    }

    public function test_sales_searches_by_invoice_number_client_nit_year_and_commercial_name(): void
    {
        $user = $this->signIn();
        $cliente = Cliente::create(['nombre' => 'Hospital Central', 'documento' => '987654', 'activo' => true]);
        Venta::create([
            'numero_venta' => 'V-2026-001', 'nombre_comercial' => 'San Benjamin', 'tipo_documento' => 'factura',
            'cliente_id' => $cliente->id, 'fecha' => '2026-05-10', 'factura' => 'FAC-801', 'nit_cliente' => '987654',
            'subtotal' => 100, 'total' => 100, 'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);
        Venta::create([
            'numero_venta' => 'V-2025-001', 'nombre_comercial' => 'Supra Medic', 'tipo_documento' => 'factura',
            'fecha' => '2025-05-10', 'factura' => 'FAC-802', 'subtotal' => 200, 'total' => 200,
            'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);

        $this->get(route('ventas.index', ['buscar' => 'FAC-801', 'anio' => 2026, 'nombre_comercial' => 'San Benjamin']))
            ->assertOk()
            ->assertSee('V-2026-001')
            ->assertDontSee('V-2025-001');

        $this->get(route('ventas.index', ['buscar' => 'Hospital Central']))
            ->assertOk()
            ->assertSee('FAC-801');
    }

    public function test_sales_searches_by_customer_business_name(): void
    {
        $user = $this->signIn();
        $cliente = Cliente::create([
            'nombre' => 'INSTITUTO MARIANO',
            'razon_social' => 'MONTEAGUDO',
            'documento' => '121971026',
            'activo' => true,
        ]);
        Venta::create([
            'numero_venta' => 'V-INSTITUTO', 'nombre_comercial' => 'San Benjamin', 'tipo_documento' => 'factura',
            'cliente_id' => $cliente->id, 'fecha' => '2026-09-20', 'factura' => 'FAC-INST', 'subtotal' => 100,
            'total' => 100, 'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);

        $this->get(route('ventas.index', ['buscar' => 'Monteagudo']))
            ->assertOk()
            ->assertSee('V-INSTITUTO')
            ->assertSee('INSTITUTO MARIANO - MONTEAGUDO');
    }

    public function test_purchases_searches_notes_and_invoices_by_number_supplier_nit_year_and_company(): void
    {
        $user = $this->signIn();
        $proveedor = Proveedor::create(['nombre' => 'Distribuidora Norte', 'documento' => '456789', 'activo' => true]);
        Compra::create([
            'numero_compra' => 'C-2026-001', 'nombre_comercial' => 'Supra Medic', 'tipo_documento' => 'nota_entrega',
            'proveedor_id' => $proveedor->id, 'fecha' => '2026-08-01', 'factura' => 'NOTA-39', 'nit_proveedor' => '456789',
            'subtotal' => 80, 'total' => 80, 'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);
        Compra::create([
            'numero_compra' => 'C-2025-001', 'nombre_comercial' => 'San Benjamin', 'tipo_documento' => 'factura',
            'fecha' => '2025-08-01', 'factura' => 'FAC-39', 'subtotal' => 90, 'total' => 90,
            'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);

        $this->get(route('compras.index', [
            'buscar' => 'Distribuidora Norte', 'tipo_documento' => 'nota_entrega', 'anio' => 2026, 'nombre_comercial' => 'Supra Medic',
        ]))->assertOk()->assertSee('C-2026-001')->assertDontSee('C-2025-001');

        $this->get(route('compras.index', ['buscar' => '456789']))
            ->assertOk()
            ->assertSee('NOTA-39');
    }

    public function test_suppliers_can_be_found_by_name_nit_phone_or_address(): void
    {
        $this->signIn();
        Proveedor::create([
            'nombre' => 'Distribuidora del Sur',
            'documento' => '456789',
            'telefono' => '64412345',
            'direccion' => 'Calle Grau 661',
            'activo' => true,
        ]);
        Proveedor::create(['nombre' => 'Proveedor no relacionado', 'documento' => '000000', 'activo' => true]);

        foreach (['Distribuidora', '456789', '64412345', 'Grau 661'] as $buscar) {
            $this->get(route('proveedores.index', ['buscar' => $buscar]))
                ->assertOk()
                ->assertSee('Distribuidora del Sur')
                ->assertDontSee('Proveedor no relacionado');
        }
    }

    public function test_sales_list_shows_pending_partial_and_paid_statuses_without_changing_document_status(): void
    {
        $user = $this->signIn();
        $pending = Venta::create([
            'numero_venta' => 'V-PENDIENTE', 'nombre_comercial' => 'San Benjamin', 'tipo_documento' => 'factura',
            'fecha' => '2026-09-01', 'subtotal' => 100, 'total' => 100, 'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);
        $partial = Venta::create([
            'numero_venta' => 'V-PARCIAL', 'nombre_comercial' => 'San Benjamin', 'tipo_documento' => 'factura',
            'fecha' => '2026-09-01', 'subtotal' => 100, 'total' => 100, 'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);
        $paid = Venta::create([
            'numero_venta' => 'V-PAGADA', 'nombre_comercial' => 'Supra Medic', 'tipo_documento' => 'factura',
            'fecha' => '2026-09-01', 'subtotal' => 100, 'total' => 100, 'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);
        foreach ([[$partial, 40], [$paid, 100]] as [$venta, $importe]) {
            Cobro::create([
                'venta_id' => $venta->id, 'usuario_id' => $user->id, 'token' => (string) \Illuminate\Support\Str::uuid(),
                'fecha' => '2026-09-18', 'importe' => $importe, 'medio' => 'transferencia', 'estado' => 'registrado',
            ]);
        }

        $this->get(route('ventas.index'))
            ->assertOk()
            ->assertSee('Pendiente')
            ->assertSee('Parcial')
            ->assertSee('Pagada')
            ->assertSee('18/09/2026');

        $this->assertSame('registrada', $pending->fresh()->estado);
        $this->assertSame('registrada', $partial->fresh()->estado);
        $this->assertSame('registrada', $paid->fresh()->estado);
    }

    public function test_customer_history_filters_sales_by_customer_year_and_document_type(): void
    {
        $user = $this->signIn();
        $cliente = Cliente::create(['nombre' => 'Laboratorio Municipal', 'documento' => '555', 'activo' => true]);
        Venta::create([
            'numero_venta' => 'V-CLIENTE-2026', 'nombre_comercial' => 'San Benjamin', 'tipo_documento' => 'factura',
            'cliente_id' => $cliente->id, 'fecha' => '2026-02-10', 'factura' => 'F-2026', 'subtotal' => 50, 'total' => 50,
            'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);
        Venta::create([
            'numero_venta' => 'NE-CLIENTE-2025', 'nombre_comercial' => 'San Benjamin', 'tipo_documento' => 'nota_entrega',
            'cliente_id' => $cliente->id, 'fecha' => '2025-02-10', 'subtotal' => 60, 'total' => 60,
            'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);

        $this->get(route('clientes.historial', [
            'cliente_id' => $cliente->id, 'anio' => 2026, 'tipo_documento' => 'factura',
        ]))->assertOk()
            ->assertSee('V-CLIENTE-2026')
            ->assertSee('Registrar cobro')
            ->assertDontSee('NE-CLIENTE-2025');
    }

    public function test_kardex_filters_movements_by_customer(): void
    {
        $user = $this->signIn();
        $clienteUno = Cliente::create(['nombre' => 'Cliente Kardex Uno', 'documento' => '701', 'activo' => true]);
        $clienteDos = Cliente::create(['nombre' => 'Cliente Kardex Dos', 'documento' => '702', 'activo' => true]);
        $producto = Producto::create([
            'codigo' => 'KDX-001',
            'nombre' => 'Producto Kardex',
            'precio_venta' => 20,
            'costo_referencial' => 10,
            'stock_minimo' => 0,
            'activo' => true,
        ]);

        $ventaUno = Venta::create([
            'numero_venta' => 'V-KARDEX-UNO', 'nombre_comercial' => 'San Benjamin', 'tipo_documento' => 'factura',
            'cliente_id' => $clienteUno->id, 'fecha' => '2026-09-10', 'subtotal' => 20, 'total' => 20,
            'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);
        $ventaDos = Venta::create([
            'numero_venta' => 'V-KARDEX-DOS', 'nombre_comercial' => 'San Benjamin', 'tipo_documento' => 'factura',
            'cliente_id' => $clienteDos->id, 'fecha' => '2026-09-10', 'subtotal' => 20, 'total' => 20,
            'estado' => 'registrada', 'usuario_id' => $user->id,
        ]);

        foreach ([[$ventaUno, 5], [$ventaDos, 3]] as [$venta, $stockNuevo]) {
            MovimientoInventario::create([
                'producto_id' => $producto->id,
                'tipo_movimiento' => 'salida',
                'cantidad' => 1,
                'stock_anterior' => $stockNuevo + 1,
                'stock_nuevo' => $stockNuevo,
                'referencia_tipo' => 'venta',
                'referencia_id' => $venta->id,
                'usuario_id' => $user->id,
                'fecha_movimiento' => '2026-09-10 10:00:00',
            ]);
        }

        $this->get(route('reportes.kardex', ['cliente_id' => $clienteUno->id]))
            ->assertOk()
            ->assertSee('Historial de cliente:')
            ->assertSee('V-KARDEX-UNO')
            ->assertDontSee('V-KARDEX-DOS');
    }
}
