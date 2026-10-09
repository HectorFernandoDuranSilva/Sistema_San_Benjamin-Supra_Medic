<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\AuditEvent;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\ConfiguracionEmpresa;
use App\Models\Cotizacion;
use App\Models\InventarioLote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\ReporteGuardado;
use App\Models\TipoCambio;
use App\Models\User;
use App\Models\Venta;
use App\Services\BcbExchangeRateService;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use App\Services\QuotationService;
use App\Services\SaleService;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_logout_requires_a_valid_csrf_token(): void
    {
        $user = User::factory()->create(['activo' => true]);

        $this->actingAs($user)
            ->withMiddleware(ValidateCsrfToken::class)
            ->post(route('logout'))
            ->assertStatus(419);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_requires_commercial_selection_before_dashboard(): void
    {
        User::factory()->create([
            'username' => 'admin123',
            'password' => 'admin123',
            'role' => 'administrador',
            'activo' => true,
        ]);

        $this->post('/login', [
            'username' => 'admin123',
            'password' => 'admin123',
        ])->assertRedirect(route('commercial.select'));

        $this->get('/dashboard')->assertRedirect(route('commercial.select'));

        $this->post(route('commercial.select.store'), [
            'commercial_name' => 'supra-medic',
        ])->assertRedirect(route('dashboard'));

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Supra Medic')
            ->assertSee('Tipo de cambio del dia');
    }

    public function test_login_and_dashboard_show_latest_exchange_rate(): void
    {
        $user = User::factory()->create([
            'username' => 'admin123',
            'password' => 'admin123',
            'role' => 'administrador',
            'activo' => true,
        ]);
        TipoCambio::create([
            'fecha' => now()->toDateString(),
            'valor' => 11.86,
            'fuente' => 'BCB',
            'estado' => 'vigente',
            'consultado_en' => now(),
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Bs 11.86');

        $this->actingAs($user)
            ->withSession(['commercial_context' => [
                'key' => 'san-benjamin',
                'name' => 'San Benjamin',
                'nit' => 'Pendiente de validacion',
                'logo' => 'assets/logos/san-benjamin.png',
            ]])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Bs 11.86');
    }

    public function test_login_auto_updates_exchange_rate_when_today_rate_is_missing(): void
    {
        config(['services.bcb.force_auto_update_in_testing' => true]);

        Producto::create([
            'codigo' => 'AUTO-USD',
            'nombre' => 'Producto recalculo automatico',
            'precio_ingreso_usd' => 10,
            'tipo_cambio' => 6.96,
            'porcentaje_utilidad' => 25,
            'precio_venta' => 87,
            'costo_referencial' => 69.60,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        Http::fake([
            config('services.bcb.exchange_rate_url') => Http::response('Tipo de cambio oficial Bolivianos por dolar estadounidense 7,25', 200),
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Bs 7.25');

        $tipoCambio = TipoCambio::where('estado', 'vigente')->latest('fecha')->latest('id')->firstOrFail();

        $this->assertSame(now()->toDateString(), $tipoCambio->fecha->toDateString());
        $this->assertSame('7.2500', $tipoCambio->valor);
        $this->assertSame('BCB', $tipoCambio->fuente);

        $producto = Producto::where('codigo', 'AUTO-USD')->firstOrFail();
        $this->assertSame('7.2500', $producto->tipo_cambio);
        $this->assertSame('72.50', $producto->costo_referencial);
        $this->assertSame('87.00', $producto->precio_venta);
    }

    public function test_login_refreshes_exchange_rate_when_stored_rate_is_stale(): void
    {
        config([
            'services.bcb.force_auto_update_in_testing' => true,
            'services.bcb.auto_update_interval_hours' => 4,
        ]);

        TipoCambio::create([
            'fecha' => now()->toDateString(),
            'valor' => 7.10,
            'fuente' => 'BCB',
            'estado' => 'vigente',
            'consultado_en' => now()->subHours(5),
        ]);

        Http::fake([
            config('services.bcb.exchange_rate_url') => Http::response('Tipo de cambio oficial Bolivianos por dolar estadounidense 7,30', 200),
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Bs 7.30');

        $tipoCambio = TipoCambio::where('estado', 'vigente')->latest('fecha')->latest('id')->firstOrFail();

        $this->assertSame('7.3000', $tipoCambio->valor);
    }

    public function test_login_does_not_refresh_exchange_rate_when_stored_rate_is_recent(): void
    {
        config([
            'services.bcb.force_auto_update_in_testing' => true,
            'services.bcb.auto_update_interval_hours' => 4,
        ]);

        TipoCambio::create([
            'fecha' => now()->toDateString(),
            'valor' => 7.10,
            'fuente' => 'BCB',
            'estado' => 'vigente',
            'consultado_en' => now()->subHours(2),
        ]);

        Http::fake([
            config('services.bcb.exchange_rate_url') => Http::response('Tipo de cambio oficial Bolivianos por dolar estadounidense 7,30', 200),
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Bs 7.10');

        Http::assertNothingSent();
    }

    public function test_dashboard_shows_operational_bottom_sections(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'Laboratorio', 'activo' => true]);
        $client = Cliente::create(['nombre' => 'Cliente dashboard', 'activo' => true]);
        $supplier = Proveedor::create(['nombre' => 'Proveedor dashboard', 'activo' => true]);

        Producto::create([
            'codigo' => 'LOW-001',
            'nombre' => 'Producto bajo',
            'categoria_id' => $category->id,
            'precio_venta' => 25,
            'costo_referencial' => 20,
            'stock_minimo' => 5,
            'activo' => true,
        ]);

        Producto::create([
            'codigo' => 'ALERT-001',
            'nombre' => 'Producto sin precio',
            'precio_venta' => 0,
            'costo_referencial' => 0,
            'stock_minimo' => 0,
            'activo' => true,
        ]);

        Venta::create([
            'numero_venta' => 'V-DASH',
            'nombre_comercial' => 'San Benjamin',
            'cliente_id' => $client->id,
            'fecha' => today(),
            'subtotal' => 100,
            'total' => 100,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);

        Compra::create([
            'numero_compra' => 'C-DASH',
            'nombre_comercial' => 'Supra Medic',
            'proveedor_id' => $supplier->id,
            'fecha' => today(),
            'subtotal' => 50,
            'total' => 50,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['commercial_context' => [
                'key' => 'san-benjamin',
                'name' => 'San Benjamin',
                'nit' => 'Pendiente de validacion',
                'logo' => 'assets/logos/san-benjamin.png',
            ]])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Movimientos de inventario')
            ->assertSee('Stock bajo')
            ->assertSee('Producto bajo')
            ->assertSee('Ultimas operaciones')
            ->assertSee('Venta V-DASH')
            ->assertSee('Compra C-DASH')
            ->assertSee('Alertas de datos')
            ->assertSee('Productos sin precio')
            ->assertSee('Producto sin precio');
    }

    public function test_dashboard_shows_billing_cut_alert_on_day_twenty_without_delivery_notes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00'));

        try {
            $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
            $client = Cliente::create(['nombre' => 'Cliente corte', 'documento' => '900', 'activo' => true]);

            Venta::create([
                'numero_venta' => 'V-CORTE-FAC-SB',
                'nombre_comercial' => 'San Benjamin',
                'tipo_documento' => 'factura',
                'cliente_id' => $client->id,
                'fecha' => '2026-09-19',
                'factura' => 'F-20-1',
                'nit_cliente' => '900',
                'subtotal' => 100,
                'descuento_porcentaje' => 0,
                'descuento' => 0,
                'impuesto_total' => 0,
                'total' => 100,
                'estado' => 'registrada',
                'usuario_id' => $user->id,
            ]);

            Venta::create([
                'numero_venta' => 'V-CORTE-FAC-SM',
                'nombre_comercial' => 'Supra Medic',
                'tipo_documento' => 'factura',
                'cliente_id' => $client->id,
                'fecha' => '2026-09-20',
                'factura' => 'F-20-2',
                'nit_cliente' => '900',
                'subtotal' => 80,
                'descuento_porcentaje' => 0,
                'descuento' => 0,
                'impuesto_total' => 0,
                'total' => 80,
                'estado' => 'registrada',
                'usuario_id' => $user->id,
            ]);

            Venta::create([
                'numero_venta' => 'V-CORTE-NOTA',
                'nombre_comercial' => 'San Benjamin',
                'tipo_documento' => 'nota_entrega',
                'cliente_id' => $client->id,
                'fecha' => '2026-09-20',
                'factura' => 'NE-20',
                'nit_cliente' => '900',
                'subtotal' => 999,
                'descuento_porcentaje' => 0,
                'descuento' => 0,
                'impuesto_total' => 0,
                'total' => 999,
                'estado' => 'registrada',
                'usuario_id' => $user->id,
            ]);

            $this->actingAs($user)
                ->withSession(['commercial_context' => ['name' => 'San Benjamin', 'logo' => 'assets/logos/san-benjamin.jpeg', 'nit' => 'Pendiente']])
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Corte de facturación al día 20')
                ->assertSee('Bs 100.00')
                ->assertSee('Bs 80.00')
                ->assertSee('Bs 180.00')
                ->assertSee('/reportes/documentos?desde=2026-09-01&amp;hasta=2026-09-20&amp;tipo_documento=factura', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_shows_end_of_month_billing_alert(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 18:00:00'));

        try {
            $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);

            Venta::create([
                'numero_venta' => 'V-CIERRE-FAC',
                'nombre_comercial' => 'San Benjamin',
                'tipo_documento' => 'factura',
                'fecha' => '2026-09-30',
                'subtotal' => 210,
                'descuento_porcentaje' => 0,
                'descuento' => 0,
                'impuesto_total' => 0,
                'total' => 210,
                'estado' => 'registrada',
                'usuario_id' => $user->id,
            ]);

            $this->actingAs($user)
                ->withSession(['commercial_context' => ['name' => 'San Benjamin', 'logo' => 'assets/logos/san-benjamin.jpeg', 'nit' => 'Pendiente']])
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Cierre de facturación mensual')
                ->assertSee('Bs 210.00');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_product_price_is_calculated_from_usd_exchange_and_operator_percentage(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'Insumos', 'activo' => true]);

        $this->actingAs($user)->post('/productos', [
            'codigo' => 'CALC-001',
            'nombre' => 'Producto calculado',
            'marca' => 'Marca de prueba', 'procedencia' => 'Bolivia',
            'categoria_id' => $category->id,
            'medida' => 'unidad',
            'ubicacion' => 'A1',
            'precio_ingreso_usd' => 10,
            'tipo_cambio' => 6.96,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => 1,
        ])->assertRedirect('/productos');

        $producto = Producto::where('codigo', 'CALC-001')->firstOrFail();

        $this->assertSame('69.60', $producto->costo_referencial);
        $this->assertSame('88.39', $producto->precio_venta);
        $this->assertSame('27.00', $producto->porcentaje_utilidad);
    }

    public function test_product_form_uses_latest_stored_exchange_rate(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        TipoCambio::create([
            'fecha' => now()->toDateString(),
            'valor' => 11.86,
            'fuente' => 'BCB',
            'estado' => 'vigente',
            'consultado_en' => now(),
        ]);

        $this->actingAs($user)
            ->get('/productos/create')
            ->assertOk()
            ->assertSee('11.86')
            ->assertSee('Fuente: BCB');
    }

    public function test_categories_and_clients_can_be_removed_safely(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $usedCategory = Categoria::create(['nombre' => 'Categoria usada', 'activo' => true]);
        $emptyCategory = Categoria::create(['nombre' => 'Categoria libre', 'activo' => true]);
        $client = Cliente::create(['nombre' => 'Cliente libre', 'activo' => true]);

        Producto::create([
            'codigo' => 'CAT-USED',
            'nombre' => 'Producto con categoria',
            'categoria_id' => $usedCategory->id,
            'precio_venta' => 10,
            'costo_referencial' => 8,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        $this->actingAs($user)->delete(route('categorias.destroy', $usedCategory))
            ->assertRedirect();
        $this->assertFalse($usedCategory->fresh()->activo);

        $this->actingAs($user)->delete(route('categorias.destroy', $emptyCategory))
            ->assertRedirect();
        $this->assertDatabaseMissing('categorias', ['id' => $emptyCategory->id]);

        $this->actingAs($user)->delete(route('clientes.destroy', $client))
            ->assertRedirect();
        $this->assertDatabaseMissing('clientes', ['id' => $client->id]);
    }

    public function test_clients_module_uses_nit_field(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);

        $this->actingAs($user)
            ->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('NIT')
            ->assertSee('NIT del cliente');

        $this->actingAs($user)
            ->post(route('clientes.store'), [
                'nombre' => 'Cliente con NIT',
                'documento' => '123456789',
                'telefono' => '70000000',
                'direccion' => 'Direccion prueba',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clientes', [
            'nombre' => 'Cliente con NIT',
            'documento' => '123456789',
        ]);
    }

    public function test_products_can_be_removed_safely_from_products_and_inventory(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'Productos eliminacion', 'activo' => true]);

        $freeProduct = Producto::create([
            'codigo' => 'DEL-FREE',
            'nombre' => 'Producto libre para borrar',
            'categoria_id' => $category->id,
            'precio_venta' => 10,
            'costo_referencial' => 8,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        $usedProduct = Producto::create([
            'codigo' => 'DEL-USED',
            'nombre' => 'Producto con historial',
            'categoria_id' => $category->id,
            'precio_venta' => 10,
            'costo_referencial' => 8,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        app(InventoryService::class)->registrarMovimiento(
            $usedProduct,
            'ajuste_positivo',
            2,
            'ajuste_manual',
            null,
            $user->id,
            'Prueba de historial'
        );

        $this->actingAs($user)
            ->get(route('productos.index'))
            ->assertOk()
            ->assertSee('Eliminar');

        $this->actingAs($user)
            ->get(route('inventario.index'))
            ->assertOk()
            ->assertSee('Eliminar');

        $this->actingAs($user)->delete(route('productos.destroy', $freeProduct))
            ->assertRedirect();
        $this->assertDatabaseMissing('productos', ['id' => $freeProduct->id]);

        $this->actingAs($user)->delete(route('productos.destroy', $usedProduct))
            ->assertRedirect();
        $this->assertDatabaseHas('productos', [
            'id' => $usedProduct->id,
            'activo' => false,
        ]);

        $this->actingAs($user)
            ->get(route('inventario.index'))
            ->assertOk()
            ->assertDontSee('Producto con historial');

        $this->actingAs($user)
            ->get(route('productos.index', ['estado' => 'inactivos']))
            ->assertOk()
            ->assertSee('Producto con historial');
    }

    public function test_inventory_can_filter_products_by_stock_range(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'Filtro de stock', 'activo' => true]);
        $lowStock = Producto::create(['codigo' => 'STOCK-LOW', 'nombre' => 'Producto stock cinco', 'categoria_id' => $category->id, 'precio_venta' => 10, 'costo_referencial' => 8, 'stock_minimo' => 0, 'activo' => true]);
        $highStock = Producto::create(['codigo' => 'STOCK-HIGH', 'nombre' => 'Producto stock veinte', 'categoria_id' => $category->id, 'precio_venta' => 10, 'costo_referencial' => 8, 'stock_minimo' => 0, 'activo' => true]);

        app(InventoryService::class)->registrarMovimiento($lowStock, 'ajuste_positivo', 5, 'ajuste_manual', null, $user->id, 'Stock para filtro');
        app(InventoryService::class)->registrarMovimiento($highStock, 'ajuste_positivo', 20, 'ajuste_manual', null, $user->id, 'Stock para filtro');

        $this->actingAs($user)
            ->get(route('inventario.index', ['stock_desde' => 0, 'stock_hasta' => 10]))
            ->assertOk()
            ->assertSee('Producto stock cinco')
            ->assertDontSee('Producto stock veinte');

        $this->actingAs($user)
            ->get(route('inventario.index', ['stock_desde' => 11, 'stock_hasta' => 10]))
            ->assertRedirect()
            ->assertSessionHasErrors('stock_hasta');
    }

    public function test_product_force_delete_requires_password_and_removes_direct_references(): void
    {
        $user = User::factory()->create([
            'role' => 'administrador',
            'activo' => true,
            'password' => 'admin123',
        ]);
        $category = Categoria::create(['nombre' => 'Forzar eliminacion', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'FORCE-DEL',
            'nombre' => 'Producto para borrar definitivamente',
            'categoria_id' => $category->id,
            'precio_venta' => 10,
            'costo_referencial' => 8,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        app(InventoryService::class)->registrarMovimiento(
            $product,
            'ajuste_positivo',
            2,
            'ajuste_manual',
            null,
            $user->id,
            'Prueba fuerza bruta'
        );

        $this->actingAs($user)->delete(route('productos.force-destroy', $product), [
            'force_password' => 'incorrecta',
        ])->assertSessionHasErrors('force_password');

        $this->assertDatabaseHas('productos', ['id' => $product->id]);
        $this->assertDatabaseHas('movimientos_inventario', ['producto_id' => $product->id]);

        $this->actingAs($user)->delete(route('productos.force-destroy', $product), [
            'force_password' => 'admin123',
        ])->assertRedirect();

        $this->assertDatabaseMissing('productos', ['id' => $product->id]);
        $this->assertDatabaseMissing('movimientos_inventario', ['producto_id' => $product->id]);
        $this->assertDatabaseHas('auditoria_eventos', [
            'accion' => 'elimino_forzado',
            'modulo' => 'productos',
            'auditable_id' => $product->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_created_products_disappear_after_delete_from_products_and_inventory_pages(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);

        $this->actingAs($user)->post(route('productos.store'), [
            'codigo' => 'UI-PROD-DEL',
            'nombre' => 'Producto borrar desde productos',
            'marca' => 'Marca de prueba', 'procedencia' => 'Bolivia',
            'precio_ingreso_usd' => 0,
            'tipo_cambio' => 6.96,
            'porcentaje_utilidad' => 25,
            'stock_minimo' => 0,
            'activo' => 1,
        ])->assertRedirect(route('productos.index'));

        $productFromProducts = Producto::where('codigo', 'UI-PROD-DEL')->firstOrFail();

        $this->actingAs($user)
            ->get(route('productos.index'))
            ->assertOk()
            ->assertSee('Producto borrar desde productos');

        $this->actingAs($user)->delete(route('productos.destroy', $productFromProducts), [
            'return_route' => 'productos.index',
        ])->assertRedirect(route('productos.index'));

        $this->actingAs($user)
            ->get(route('productos.index'))
            ->assertOk()
            ->assertDontSee('Producto borrar desde productos');

        $this->actingAs($user)->post(route('productos.store'), [
            'codigo' => 'UI-INV-DEL',
            'nombre' => 'Producto borrar desde inventario',
            'marca' => 'Marca de prueba', 'procedencia' => 'Bolivia',
            'precio_ingreso_usd' => 0,
            'tipo_cambio' => 6.96,
            'porcentaje_utilidad' => 25,
            'stock_minimo' => 0,
            'activo' => 1,
        ])->assertRedirect(route('productos.index'));

        $productFromInventory = Producto::where('codigo', 'UI-INV-DEL')->firstOrFail();

        $this->actingAs($user)
            ->get(route('inventario.index'))
            ->assertOk()
            ->assertSee('Producto borrar desde inventario');

        $this->actingAs($user)->delete(route('productos.destroy', $productFromInventory), [
            'return_route' => 'inventario.index',
        ])->assertRedirect(route('inventario.index'));

        $this->actingAs($user)
            ->get(route('inventario.index'))
            ->assertOk()
            ->assertDontSee('Producto borrar desde inventario');
    }

    public function test_product_can_be_created_with_initial_stock_batch_and_expiration(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);

        $this->actingAs($user)
            ->get(route('productos.create'))
            ->assertOk()
            ->assertSee('Stock inicial')
            ->assertSee('Número de lote inicial')
            ->assertSee('Fecha de vencimiento inicial');

        $this->actingAs($user)->post(route('productos.store'), [
            'codigo' => 'PROD-LOTE-INI',
            'nombre' => 'Producto con lote inicial',
            'marca' => 'Marca de prueba', 'procedencia' => 'Bolivia',
            'medida' => 'CAJA',
            'precio_ingreso_usd' => 10,
            'tipo_cambio' => 7,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'stock_inicial' => 6,
            'codigo_lote' => 'INI-2026-A',
            'fecha_vencimiento' => '2026-12-31',
            'activo' => 1,
        ])->assertRedirect(route('productos.index'));

        $product = Producto::where('codigo', 'PROD-LOTE-INI')->firstOrFail();

        $this->assertSame(6.0, app(InventoryService::class)->stockActual($product));
        $this->assertDatabaseHas('inventario_lotes', [
            'producto_id' => $product->id,
            'codigo_lote' => 'INI-2026-A',
            'fecha_vencimiento' => '2026-12-31 00:00:00',
            'cantidad_inicial' => 6,
            'cantidad_disponible' => 6,
        ]);

        $this->actingAs($user)
            ->get(route('productos.edit', $product))
            ->assertOk()
            ->assertSee('Lotes y vencimientos del producto')
            ->assertSee('INI-2026-A')
            ->assertSee('31/12/2026')
            ->assertSee('Agregar lote al producto')
            ->assertSee(route('productos.show', $product), false)
            ->assertSee(route('inventario.lotes.index', ['producto_id' => $product->id]), false);

        $this->actingAs($user)
            ->get(route('productos.show', $product))
            ->assertOk()
            ->assertSee('Lotes asociados')
            ->assertSee('INI-2026-A')
            ->assertSee('31/12/2026');

        $this->actingAs($user)->post(route('productos.lotes.store', $product), [
            'cantidad' => 4,
            'codigo_lote' => 'EDIT-2027-B',
            'fecha_vencimiento' => '2027-05-15',
            'costo_unitario' => 70,
            'observacion' => 'Lote agregado desde prueba',
        ])->assertRedirect(route('productos.show', $product));

        $this->assertSame(10.0, app(InventoryService::class)->stockActual($product));
        $this->assertDatabaseHas('inventario_lotes', [
            'producto_id' => $product->id,
            'codigo_lote' => 'EDIT-2027-B',
            'fecha_vencimiento' => '2027-05-15 00:00:00',
            'cantidad_inicial' => 4,
            'cantidad_disponible' => 4,
        ]);

        $this->actingAs($user)
            ->get(route('productos.show', $product))
            ->assertOk()
            ->assertSee('EDIT-2027-B')
            ->assertSee('15/05/2027');
    }

    public function test_product_batch_fields_require_initial_stock(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);

        $this->actingAs($user)->post(route('productos.store'), [
            'codigo' => 'PROD-LOTE-SIN-STOCK',
            'nombre' => 'Producto lote sin stock',
            'marca' => 'Marca de prueba', 'procedencia' => 'Bolivia',
            'precio_ingreso_usd' => 10,
            'tipo_cambio' => 7,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'stock_inicial' => 0,
            'codigo_lote' => 'SIN-STOCK',
            'activo' => 1,
        ])->assertSessionHasErrors('stock_inicial');
    }

    public function test_bcb_exchange_rate_parser_extracts_official_rate(): void
    {
        $html = '<section>Tipo de cambio oficial Bolivianos por dolar estadounidense 11,86</section>';

        $this->assertSame(11.86, app(BcbExchangeRateService::class)->extractOfficialExchangeRate($html));

        $officialTable = 'ESTADOS UNIDOS DÓLAR USD 11.71';

        $this->assertSame(11.71, app(BcbExchangeRateService::class)->extractOfficialExchangeRate($officialTable));
    }

    public function test_bcb_exchange_rate_uses_last_known_value_when_remote_fails(): void
    {
        TipoCambio::create([
            'fecha' => now()->subDay()->toDateString(),
            'valor' => 11.71,
            'fuente' => 'BCB',
            'estado' => 'vigente',
            'consultado_en' => now()->subDay(),
        ]);

        Http::fake([
            config('services.bcb.exchange_rate_url') => Http::response('error temporal', 500),
        ]);

        $tipoCambio = app(BcbExchangeRateService::class)->updateFromBcb();

        $this->assertSame(now()->toDateString(), $tipoCambio->fecha->toDateString());
        $this->assertSame('11.7100', $tipoCambio->valor);
        $this->assertSame('BCB respaldo local', $tipoCambio->fuente);
        $this->assertStringContainsString('No se pudo actualizar desde el BCB', $tipoCambio->observacion);
    }

    public function test_bcb_update_recalculates_product_prices_when_dollar_fluctuates(): void
    {
        Producto::create([
            'codigo' => 'USD-001',
            'nombre' => 'Producto con costo dolar',
            'precio_ingreso_usd' => 10,
            'tipo_cambio' => 6.96,
            'porcentaje_utilidad' => 25,
            'costo_referencial' => 69.60,
            'precio_venta' => 87.00,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        Producto::create([
            'codigo' => 'SIN-USD',
            'nombre' => 'Producto sin costo dolar',
            'moneda_costo' => 'bs',
            'precio_ingreso_usd' => 0,
            'tipo_cambio' => 6.96,
            'porcentaje_utilidad' => 27,
            'costo_referencial' => 40,
            'precio_venta' => 50.80,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        Http::fake([
            config('services.bcb.exchange_rate_url') => Http::response('Tipo de cambio oficial Bolivianos por dolar estadounidense 7,10', 200),
        ]);

        $this->artisan('bcb:actualizar-tipo-cambio')
            ->expectsOutput('Tipo de cambio BCB actualizado: 7.1000 para ' . now()->toDateString())
            ->expectsOutput('Productos recalculados por fluctuacion del dolar: 2')
            ->assertExitCode(0);

        $productoUsd = Producto::where('codigo', 'USD-001')->firstOrFail();
        $productoSinUsd = Producto::where('codigo', 'SIN-USD')->firstOrFail();

        $this->assertSame('7.1000', $productoUsd->tipo_cambio);
        $this->assertSame('71.00', $productoUsd->costo_referencial);
        $this->assertSame('87.00', $productoUsd->precio_venta);
        $this->assertSame('7.1000', $productoSinUsd->tipo_cambio);
        $this->assertSame('40.00', $productoSinUsd->costo_referencial);
        $this->assertSame('50.80', $productoSinUsd->precio_venta);
    }

    public function test_admin_can_create_product_with_cost_in_bolivianos(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'activo' => true]);

        $this->actingAs($admin)
            ->post(route('productos.store'), [
                'codigo' => 'BS-001',
                'nombre' => 'Producto costo bolivianos',
                'marca' => 'Marca de prueba', 'procedencia' => 'Bolivia',
                'moneda_costo' => 'bs',
                'costo_ingreso_bs' => 80,
                'precio_ingreso_usd' => 0,
                'tipo_cambio' => 10,
                'porcentaje_utilidad' => 25,
                'stock_minimo' => 1,
                'activo' => 1,
            ])
            ->assertRedirect(route('productos.index'));

        $producto = Producto::where('codigo', 'BS-001')->firstOrFail();

        $this->assertSame('bs', $producto->moneda_costo);
        $this->assertSame('0.00', $producto->precio_ingreso_usd);
        $this->assertSame('80.00', $producto->costo_referencial);
        $this->assertSame('100.00', $producto->precio_venta);
    }

    public function test_sales_and_quotes_expose_current_replacement_suggested_price(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        TipoCambio::create([
            'fecha' => now()->toDateString(),
            'valor' => 11.71,
            'fuente' => 'BCB',
            'estado' => 'vigente',
            'consultado_en' => now(),
        ]);
        Producto::create([
            'codigo' => 'SUG-USD',
            'nombre' => 'Producto sugerido dolar',
            'moneda_costo' => 'usd',
            'precio_ingreso_usd' => 10,
            'tipo_cambio' => 6.96,
            'porcentaje_utilidad' => 25,
            'costo_referencial' => 69.60,
            'precio_venta' => 100,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        $this->actingAs($admin)
            ->withSession(['commercial_context' => ['name' => 'San Benjamin']])
            ->get(route('ventas.create'))
            ->assertOk()
            ->assertSee('Precio sugerido por dólar/reposición')
            ->assertSee('"suggested":146.38', false);

        $this->actingAs($admin)
            ->withSession(['commercial_context' => ['name' => 'San Benjamin']])
            ->get(route('cotizaciones.create'))
            ->assertOk()
            ->assertSee('Precio sugerido por dólar/reposición')
            ->assertSee('"suggested":146.38', false);
    }

    public function test_sale_decreases_stock_and_annulment_restores_it(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'Kits', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'P001',
            'nombre' => 'Producto prueba',
            'categoria_id' => $category->id,
            'precio_venta' => 10,
            'costo_referencial' => 5,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        $inventory = app(InventoryService::class);
        $inventory->registrarMovimiento($product, 'entrada', 5, 'test', null, $user->id, 'Stock test');

        $sale = app(SaleService::class)->registrar([
            'numero_venta' => 'V-TEST',
            'fecha' => now()->toDateString(),
            'descuento' => 0,
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 2, 'precio_unitario' => 10],
            ],
        ], $user->id);

        $this->assertSame(3.0, $inventory->stockActual($product));

        app(SaleService::class)->anular($sale, $user->id);

        $this->assertSame(5.0, $inventory->stockActual($product));
    }

    public function test_sale_form_extra_fields_are_calculated_and_stored(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'Ventas', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'SALE-001',
            'nombre' => 'Producto venta',
            'categoria_id' => $category->id,
            'precio_ingreso_usd' => 10,
            'tipo_cambio' => 6.96,
            'porcentaje_utilidad' => 27,
            'precio_venta' => 100,
            'costo_referencial' => 70,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        app(InventoryService::class)->registrarMovimiento($product, 'entrada', 10, 'test', null, $user->id, 'Stock test');

        $sale = app(SaleService::class)->registrar([
            'numero_venta' => 'V-EXTRA',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => now()->toDateString(),
            'factura' => 'F-10',
            'nit_cliente' => '123456',
            'lista_precios' => 'Precio de Venta',
            'descuento_porcentaje' => 10,
            'observacion' => 'Comentario test',
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 2,
                    'precio_unitario' => 100,
                    'descuento' => 20,
                    'impuesto' => 5,
                    'costo_envio' => 3,
                    'otros_costos' => 2,
                ],
            ],
        ], $user->id);

        $detail = $sale->detalles->first();

        $this->assertSame('San Benjamin', $sale->nombre_comercial);
        $this->assertSame('F-10', $sale->factura);
        $this->assertSame('123456', $sale->nit_cliente);
        $this->assertSame('200.00', $sale->subtotal);
        $this->assertSame('20.00', $sale->descuento);
        $this->assertSame('5.00', $sale->impuesto_total);
        $this->assertSame('3.00', $sale->costo_envio_total);
        $this->assertSame('2.00', $sale->otros_costos_total);
        $this->assertSame('190.00', $sale->total);
        $this->assertSame('190.00', $detail->total);
    }

    public function test_sale_create_form_shows_excel_like_fields(): void
    {
        $user = User::factory()->create(['role' => 'operador', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'Formulario', 'activo' => true]);
        Producto::create([
            'codigo' => 'FORM-001',
            'nombre' => 'Producto formulario',
            'categoria_id' => $category->id,
            'precio_venta' => 100,
            'costo_referencial' => 80,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['commercial_context' => [
                'key' => 'san-benjamin',
                'name' => 'San Benjamin',
                'nit' => 'Pendiente de validacion',
                'logo' => 'assets/logos/san-benjamin.png',
            ]])
            ->get('/ventas/create')
            ->assertOk()
            ->assertSee('Registro de ventas')
            ->assertDontSee('purchase-screen')
            ->assertDontSee('REGISTRO DE COMPRAS')
            ->assertSee('Orden')
            ->assertSee('Factura')
            ->assertSee('NIT')
            ->assertSee('Lista de precios')
            ->assertSee('Existencia')
            ->assertSee('Lote')
            ->assertSee('Vencimiento')
            ->assertSee('Costo envío')
            ->assertSee('Otros costos')
            ->assertSee('Cliente')
            ->assertSee('Comentario')
            ->assertSee('Totales')
            ->assertSee('venta-items-alert')
            ->assertDontSee('items[0]', false);
    }

    public function test_purchase_recargo_updates_suggestion_without_replacing_sale_price(): void
    {
        $user = User::factory()->create(['role' => 'operador', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'Compras margen', 'activo' => true]);
        $proveedor = Proveedor::create(['nombre' => 'Proveedor margen', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'MARGIN-001',
            'nombre' => 'Producto margen',
            'categoria_id' => $category->id,
            'precio_venta' => 0,
            'costo_referencial' => 0,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        $this->actingAs($user)
            ->post('/compras', [
                'numero_compra' => 'C-BAD-MARGIN',
                'nombre_comercial' => 'San Benjamin',
                'proveedor_id' => $proveedor->id,
                'fecha' => now()->toDateString(),
                'items' => [
                    [
                        'producto_id' => $product->id,
                        'cantidad' => 1,
                        'costo_unitario' => 100,
                        'porcentaje_utilidad' => 24.99,
                    ],
                ],
            ])
            ->assertSessionHasErrors('items.0.porcentaje_utilidad');

        $purchase = app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-GOOD-MARGIN',
            'nombre_comercial' => 'San Benjamin',
            'proveedor_id' => $proveedor->id,
            'fecha' => now()->toDateString(),
            'factura' => 'FAC-25',
            'nit_proveedor' => '123456',
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 2,
                    'costo_unitario' => 100,
                    'porcentaje_utilidad' => 25.5,
                    'impuesto' => 4,
                ],
            ],
        ], $user->id);

        $product->refresh();

        $this->assertSame('25.50', $purchase->detalles->first()->porcentaje_utilidad);
        $this->assertSame('FAC-25', $purchase->factura);
        $this->assertSame('123456', $purchase->nit_proveedor);
        $this->assertSame('200.00', $purchase->subtotal);
        $this->assertSame('4.00', $purchase->impuesto_total);
        $this->assertSame('204.00', $purchase->total);
        $this->assertSame('4.00', $purchase->detalles->first()->impuesto);
        $this->assertSame('204.00', $purchase->detalles->first()->total);
        $this->assertSame('100.000000', $product->ultimo_costo_compra);
        $this->assertSame('25.50', $product->porcentaje_utilidad);
        $this->assertSame('0.00', $product->precio_venta);
        $this->assertSame(125.50, $product->precio_sugerido);
    }

    public function test_purchase_create_form_shows_excel_like_fields(): void
    {
        $user = User::factory()->create(['role' => 'operador', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'Formulario compras', 'activo' => true]);
        Producto::create([
            'codigo' => 'BUY-FORM',
            'nombre' => 'Producto formulario compra',
            'categoria_id' => $category->id,
            'precio_venta' => 125,
            'costo_referencial' => 100,
            'porcentaje_utilidad' => 25,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['commercial_context' => [
                'key' => 'san-benjamin',
                'name' => 'San Benjamin',
                'nit' => 'Pendiente de validacion',
                'logo' => 'assets/logos/san-benjamin.png',
            ]])
            ->get('/compras/create')
            ->assertOk()
            ->assertSee('Registro de compras')
            ->assertDontSee('purchase-screen')
            ->assertSee('Fecha')
            ->assertSee('Factura')
            ->assertSee('NIT')
            ->assertSee('Buscar proveedor')
            ->assertSee('Nombre o NIT')
            ->assertSee('Proveedor')
            ->assertSee('Comentario')
            ->assertSee('No.')
            ->assertSee('Código')
            ->assertSee('Producto')
            ->assertSee('Costo Unit')
            ->assertSee('Desc %')
            ->assertSee('Impuesto')
            ->assertSee('compra-items-alert')
            ->assertDontSee('items[0]', false);
    }

    public function test_purchase_can_be_registered_through_http_with_excel_like_fields(): void
    {
        $user = User::factory()->create(['role' => 'operador', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'HTTP compras', 'activo' => true]);
        $provider = Proveedor::create([
            'nombre' => 'Proveedor HTTP',
            'documento' => 'NIT-HTTP',
            'activo' => true,
        ]);
        $product = Producto::create([
            'codigo' => 'BUY-HTTP',
            'nombre' => 'Producto compra HTTP',
            'categoria_id' => $category->id,
            'precio_venta' => 0,
            'costo_referencial' => 0,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['commercial_context' => [
                'key' => 'supra-medic',
                'name' => 'Supra Medic',
                'nit' => 'Pendiente de validacion',
                'logo' => 'assets/logos/supra-medic.png',
            ]])
            ->post('/compras', [
                'numero_compra' => 'C-HTTP-COMPRA',
                'nombre_comercial' => 'Supra Medic',
                'proveedor_id' => $provider->id,
                'fecha' => now()->toDateString(),
                'factura' => 'FAC-HTTP',
                'nit_proveedor' => 'NIT-HTTP',
                'observacion' => 'Compra desde HTTP',
                'items' => [
                    [
                        'producto_id' => $product->id,
                        'cantidad' => 3,
                        'costo_unitario' => 40,
                        'porcentaje_utilidad' => 26,
                        'impuesto' => 5,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('compras', [
            'numero_compra' => 'C-HTTP-COMPRA',
            'nombre_comercial' => 'Supra Medic',
            'factura' => 'FAC-HTTP',
            'nit_proveedor' => 'NIT-HTTP',
            'subtotal' => 120,
            'impuesto_total' => 5,
            'total' => 125,
        ]);

        $this->assertDatabaseHas('detalle_compras', [
            'codigo_producto' => 'BUY-HTTP',
            'cantidad' => 3,
            'costo_unitario' => 40,
            'porcentaje_utilidad' => 26,
            'subtotal' => 120,
            'impuesto' => 5,
            'total' => 125,
        ]);

        $product->refresh();

        $this->assertSame('40.000000', $product->ultimo_costo_compra);
        $this->assertSame('26.00', $product->porcentaje_utilidad);
        $this->assertSame('0.00', $product->precio_venta);
        $this->assertSame(50.40, $product->precio_sugerido);
        $this->assertSame(3.0, app(InventoryService::class)->stockActual($product));
    }

    public function test_purchase_rejects_margin_above_allowed_range(): void
    {
        $user = User::factory()->create(['role' => 'operador', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'Compras rango alto', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'BUY-BAD-RANGE',
            'nombre' => 'Producto rango invalido',
            'categoria_id' => $category->id,
            'precio_venta' => 0,
            'costo_referencial' => 0,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        $this->actingAs($user)
            ->post('/compras', [
                'numero_compra' => 'C-BAD-RANGE',
                'nombre_comercial' => 'San Benjamin',
                'fecha' => now()->toDateString(),
                'items' => [
                    [
                        'producto_id' => $product->id,
                        'cantidad' => 1,
                        'costo_unitario' => 40,
                        'porcentaje_utilidad' => 30.01,
                        'impuesto' => 0,
                    ],
                ],
            ])
            ->assertSessionHasErrors('items.0.porcentaje_utilidad');

        $this->assertDatabaseMissing('compras', ['numero_compra' => 'C-BAD-RANGE']);
        $this->assertSame(0.0, app(InventoryService::class)->stockActual($product));
    }

    public function test_sale_can_be_registered_through_http_with_new_fields(): void
    {
        $user = User::factory()->create(['role' => 'operador', 'activo' => true]);
        $cliente = Cliente::create(['nombre' => 'Cliente prueba', 'documento' => '789', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'HTTP ventas', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'HTTP-001',
            'nombre' => 'Producto HTTP',
            'categoria_id' => $category->id,
            'precio_venta' => 50,
            'costo_referencial' => 30,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        app(InventoryService::class)->registrarMovimiento($product, 'entrada', 5, 'test', null, $user->id, 'Stock test');

        $this->actingAs($user)
            ->withSession(['commercial_context' => [
                'key' => 'supra-medic',
                'name' => 'Supra Medic',
                'nit' => 'Pendiente de validacion',
                'logo' => 'assets/logos/supra-medic.png',
            ]])
            ->post('/ventas', [
                'numero_venta' => 'V-HTTP',
                'nombre_comercial' => 'Supra Medic',
                'cliente_id' => $cliente->id,
                'fecha' => now()->toDateString(),
                'factura' => 'F-HTTP',
                'nit_cliente' => '789',
                'lista_precios' => 'Precio de Venta',
                'descuento_porcentaje' => 10,
                'observacion' => 'Venta desde HTTP',
                'items' => [
                    [
                        'producto_id' => $product->id,
                        'cantidad' => 2,
                        'precio_unitario' => 50,
                        'impuesto' => 1,
                        'costo_envio' => 2,
                        'otros_costos' => 3,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ventas', [
            'numero_venta' => 'V-HTTP',
            'nombre_comercial' => 'Supra Medic',
            'factura' => 'F-HTTP',
            'nit_cliente' => '789',
            'subtotal' => 100,
            'descuento' => 10,
            'impuesto_total' => 1,
            'costo_envio_total' => 2,
            'otros_costos_total' => 3,
            'total' => 96,
        ]);
        $this->assertSame(3.0, app(InventoryService::class)->stockActual($product));
    }

    public function test_receipt_previews_render_inline_pdf(): void
    {
        $user = User::factory()->create(['role' => 'operador', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'Logos recibos', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'LOGO-001',
            'nombre' => 'Producto logo',
            'categoria_id' => $category->id,
            'precio_venta' => 50,
            'costo_referencial' => 40,
            'porcentaje_utilidad' => 25,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        app(InventoryService::class)->registrarMovimiento($product, 'entrada', 5, 'test', null, $user->id, 'Stock logo test');

        $sale = app(SaleService::class)->registrar([
            'numero_venta' => 'V-LOGO-PREVIEW',
            'nombre_comercial' => 'Supra Medic',
            'fecha' => now()->toDateString(),
            'descuento' => 0,
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 1, 'precio_unitario' => 50],
            ],
        ], $user->id);

        $purchase = app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-LOGO-PREVIEW',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => now()->toDateString(),
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 1,
                    'costo_unitario' => 40,
                    'porcentaje_utilidad' => 25,
                    'impuesto' => 0,
                ],
            ],
        ], $user->id);

        $this->actingAs($user)
            ->get(route('ventas.recibo', $sale))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('compras.recibo', $purchase))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_sale_with_new_fields_still_rejects_insufficient_stock(): void
    {
        $user = User::factory()->create(['role' => 'operador', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'Stock venta', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'NO-STOCK',
            'nombre' => 'Producto sin stock',
            'categoria_id' => $category->id,
            'precio_venta' => 50,
            'costo_referencial' => 30,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        $this->actingAs($user)
            ->post('/ventas', [
                'numero_venta' => 'V-NOSTOCK',
                'nombre_comercial' => 'San Benjamin',
                'fecha' => now()->toDateString(),
                'factura' => 'F-NO',
                'nit_cliente' => '0',
                'lista_precios' => 'Precio de Venta',
                'descuento_porcentaje' => 0,
                'items' => [
                    [
                        'producto_id' => $product->id,
                        'cantidad' => 1,
                        'precio_unitario' => 50,
                        'impuesto' => 0,
                        'costo_envio' => 0,
                        'otros_costos' => 0,
                    ],
                ],
            ])
            ->assertSessionHasErrors('venta');

        $this->assertDatabaseMissing('ventas', ['numero_venta' => 'V-NOSTOCK']);
    }

    public function test_sales_and_purchases_reject_empty_prices(): void
    {
        $validation = validator([
            'items' => [['precio_unitario' => 10], ['precio_unitario' => 0]],
        ], ['items.*.precio_unitario' => ['required', 'numeric', 'gt:0']]);
        $this->assertSame(
            'El campo precio unitario de la fila 2 debe ser mayor que 0.',
            $validation->errors()->first('items.1.precio_unitario')
        );
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'Validaciones fuertes', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'VAL-PRICE',
            'nombre' => 'Producto validacion precio',
            'categoria_id' => $category->id,
            'precio_venta' => 50,
            'costo_referencial' => 20,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        app(InventoryService::class)->registrarMovimiento($product, 'ajuste_positivo', 3, 'test', null, $user->id, 'Stock para validacion');

        $this->actingAs($user)->post('/ventas', [
            'numero_venta' => 'V-ZERO-PRICE',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'fecha' => now()->toDateString(),
            'descuento_porcentaje' => 0,
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 1, 'precio_unitario' => 0],
            ],
        ])->assertSessionHasErrors([
            'items.0.precio_unitario' => 'El campo precio unitario de la fila 1 debe ser mayor que 0.',
        ]);

        $this->view('partials.flash', ['errors' => session('errors')])
            ->assertSee('Revise la información')
            ->assertSee('El campo precio unitario de la fila 1 debe ser mayor que 0.')
            ->assertDontSee('field must');

        $this->assertDatabaseMissing('ventas', ['numero_venta' => 'V-ZERO-PRICE']);

        $this->actingAs($user)->post(route('notas.store'), [
            'numero_venta' => 'NE-ZERO-PRICE',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'nota_entrega',
            'fecha' => now()->toDateString(),
            'descuento_porcentaje' => 0,
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 1, 'precio_unitario' => 0],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('ventas', ['numero_venta' => 'NE-ZERO-PRICE', 'tipo_documento' => 'nota_entrega', 'total' => 0]);

        $this->actingAs($user)->post('/compras', [
            'numero_compra' => 'C-ZERO-COST',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'fecha' => now()->toDateString(),
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 1, 'costo_unitario' => 0, 'porcentaje_utilidad' => 27, 'impuesto' => 0],
            ],
        ])->assertSessionHasErrors([
            'items.0.costo_unitario' => 'El campo costo unitario de la fila 1 debe ser mayor que 0.',
        ]);

        $this->assertDatabaseMissing('compras', ['numero_compra' => 'C-ZERO-COST']);
    }

    public function test_sale_rejects_expired_batches_and_hides_them_from_selector(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 10:00:00'));

        try {
            $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
            $category = Categoria::create(['nombre' => 'Vencidos', 'activo' => true]);
            $product = Producto::create([
                'codigo' => 'LOT-EXPIRED',
                'nombre' => 'Producto lote vencido',
                'categoria_id' => $category->id,
                'precio_venta' => 100,
                'costo_referencial' => 60,
                'stock_minimo' => 1,
                'activo' => true,
            ]);

            app(PurchaseService::class)->registrar([
                'numero_compra' => 'C-EXPIRED-LOT',
                'nombre_comercial' => 'San Benjamin',
                'tipo_documento' => 'factura',
                'fecha' => now()->subMonth()->toDateString(),
                'items' => [
                    ['producto_id' => $product->id, 'cantidad' => 2, 'costo_unitario' => 60, 'porcentaje_utilidad' => 27, 'codigo_lote' => 'VENCIDO-01', 'fecha_vencimiento' => now()->subDay()->toDateString(), 'impuesto' => 0],
                ],
            ], $user->id);

            $this->actingAs($user)
                ->withSession(['commercial_context' => ['name' => 'San Benjamin', 'logo' => 'assets/logos/san-benjamin.jpeg', 'nit' => 'Pendiente']])
                ->get('/ventas/create')
                ->assertOk()
                ->assertDontSee('VENCIDO-01');

            $expiredBatch = InventarioLote::where('codigo_lote', 'VENCIDO-01')->firstOrFail();

            $this->actingAs($user)->post('/ventas', [
                'numero_venta' => 'V-EXPIRED-LOT',
                'nombre_comercial' => 'San Benjamin',
                'tipo_documento' => 'factura',
                'fecha' => now()->toDateString(),
                'descuento_porcentaje' => 0,
                'items' => [
                    [
                        'producto_id' => $product->id,
                        'inventario_lote_id' => $expiredBatch->id,
                        'fecha_vencimiento' => now()->subDay()->toDateString(),
                        'cantidad' => 1,
                        'precio_unitario' => 100,
                    ],
                ],
            ])->assertSessionHasErrors('items.0.fecha_vencimiento');

            $this->assertDatabaseMissing('ventas', ['numero_venta' => 'V-EXPIRED-LOT']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_purchase_creates_expiration_batch_and_sale_consumes_nearest_expiration_first(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $category = Categoria::create(['nombre' => 'Lotes FEFO', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'FEFO-001',
            'nombre' => 'Producto con vencimiento',
            'categoria_id' => $category->id,
            'precio_venta' => 100,
            'costo_referencial' => 50,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-FEFO-1',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => now()->toDateString(),
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 2, 'costo_unitario' => 50, 'porcentaje_utilidad' => 27, 'fecha_vencimiento' => now()->addDays(60)->toDateString(), 'impuesto' => 0],
            ],
        ], $user->id);

        app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-FEFO-2',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => now()->toDateString(),
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 3, 'costo_unitario' => 50, 'porcentaje_utilidad' => 27, 'fecha_vencimiento' => now()->addDays(10)->toDateString(), 'impuesto' => 0],
            ],
        ], $user->id);

        app(SaleService::class)->registrar([
            'numero_venta' => 'V-FEFO',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => now()->toDateString(),
            'descuento_porcentaje' => 0,
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 4, 'precio_unitario' => 100, 'descuento' => 0, 'impuesto' => 0],
            ],
        ], $user->id);

        $nearest = InventarioLote::where('producto_id', $product->id)->orderBy('fecha_vencimiento')->first();
        $later = InventarioLote::where('producto_id', $product->id)->orderByDesc('fecha_vencimiento')->first();

        $this->assertSame('0.00', $nearest->cantidad_disponible);
        $this->assertSame('1.00', $later->cantidad_disponible);
        $this->assertSame(1.0, app(InventoryService::class)->stockActual($product));
        $this->assertDatabaseCount('detalle_venta_lotes', 2);
    }

    public function test_sale_can_use_manually_selected_batch_and_expiration_date(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'MAN-LOT',
            'nombre' => 'Producto lote manual',
            'precio_venta' => 100,
            'costo_referencial' => 50,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-MAN-NEAR',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => now()->toDateString(),
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 2, 'costo_unitario' => 50, 'porcentaje_utilidad' => 27, 'codigo_lote' => 'LOTE-CERCANO-MAN', 'fecha_vencimiento' => now()->addDays(5)->toDateString(), 'impuesto' => 0],
            ],
        ], $user->id);

        app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-MAN-FAR',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => now()->toDateString(),
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 2, 'costo_unitario' => 50, 'porcentaje_utilidad' => 27, 'codigo_lote' => 'LOTE-LEJANO-MAN', 'fecha_vencimiento' => now()->addDays(80)->toDateString(), 'impuesto' => 0],
            ],
        ], $user->id);

        $nearBatch = InventarioLote::where('codigo_lote', 'LOTE-CERCANO-MAN')->firstOrFail();
        $farBatch = InventarioLote::where('codigo_lote', 'LOTE-LEJANO-MAN')->firstOrFail();
        $manualDate = now()->addDays(90)->toDateString();

        $sale = app(SaleService::class)->registrar([
            'numero_venta' => 'V-MAN-LOT',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => now()->toDateString(),
            'descuento_porcentaje' => 0,
            'items' => [
                [
                    'producto_id' => $product->id,
                    'inventario_lote_id' => $farBatch->id,
                    'fecha_vencimiento' => $manualDate,
                    'cantidad' => 1,
                    'precio_unitario' => 100,
                    'descuento' => 0,
                    'impuesto' => 0,
                ],
            ],
        ], $user->id);

        $this->assertSame('2.00', $nearBatch->fresh()->cantidad_disponible);
        $this->assertSame('1.00', $farBatch->fresh()->cantidad_disponible);
        $this->assertDatabaseHas('detalle_venta_lotes', [
            'detalle_venta_id' => $sale->detalles->first()->id,
            'inventario_lote_id' => $farBatch->id,
            'fecha_vencimiento' => $manualDate . ' 00:00:00',
        ]);
    }

    public function test_purchase_registers_document_type_batch_code_and_suggested_sale_price(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $provider = Proveedor::create(['nombre' => 'Proveedor lote sprint 2', 'documento' => '778899', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'SPR2-LOT',
            'nombre' => 'Producto lote sprint 2',
            'precio_venta' => 0,
            'costo_referencial' => 0,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        $purchase = app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-SPR2-LOT',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'nota_entrega',
            'proveedor_id' => $provider->id,
            'fecha' => now()->toDateString(),
            'factura' => 'NE-445',
            'nit_proveedor' => '778899',
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 4,
                    'costo_unitario' => 100,
                    'porcentaje_utilidad' => 26.5,
                    'codigo_lote' => 'LOT-2026-A',
                    'fecha_vencimiento' => now()->addMonths(8)->toDateString(),
                    'impuesto' => 0,
                ],
            ],
        ], $user->id);

        $detail = $purchase->detalles->first();
        $batch = InventarioLote::where('detalle_compra_id', $detail->id)->firstOrFail();
        $product->refresh();

        $this->assertSame('nota_entrega', $purchase->tipo_documento);
        $this->assertSame('LOT-2026-A', $detail->codigo_lote);
        $this->assertSame('LOT-2026-A', $batch->codigo_lote);
        $this->assertSame('4.00', $batch->cantidad_disponible);
        $this->assertSame('0.00', $product->precio_venta);
        $this->assertSame(126.50, $product->precio_sugerido);
        $this->assertSame(4.0, app(InventoryService::class)->stockActual($product));
    }

    public function test_sale_registers_document_type_and_shows_consumed_batch_code(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $client = Cliente::create(['nombre' => 'Cliente con NIT venta', 'documento' => '556677', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'SALE-LOT',
            'nombre' => 'Producto venta lote',
            'precio_venta' => 80,
            'costo_referencial' => 50,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-SALE-LOT-A',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'fecha' => now()->toDateString(),
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 1,
                    'costo_unitario' => 50,
                    'porcentaje_utilidad' => 27,
                    'codigo_lote' => 'LOTE-LEJANO',
                    'fecha_vencimiento' => now()->addDays(40)->toDateString(),
                    'impuesto' => 0,
                ],
            ],
        ], $user->id);

        app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-SALE-LOT-B',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'fecha' => now()->toDateString(),
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 2,
                    'costo_unitario' => 50,
                    'porcentaje_utilidad' => 27,
                    'codigo_lote' => 'LOTE-CERCANO',
                    'fecha_vencimiento' => now()->addDays(5)->toDateString(),
                    'impuesto' => 0,
                ],
            ],
        ], $user->id);

        $this->actingAs($user)->get(route('reportes.documentos'))->assertOk()
            ->assertSee(route('notas.create'), false)
            ->assertSee('Crear nota de entrega');
        $this->get(route('notas.create'))->assertOk()
            ->assertViewHas('tipoDocumento', 'nota_entrega')
            ->assertSee('Nueva nota de entrega')
            ->assertSee('Registrar nota de entrega');
        $this->post(route('notas.store'), [
            'numero_venta' => 'V-DOC-LOT',
            'nombre_comercial' => 'Supra Medic',
            'tipo_documento' => 'nota_entrega',
            'cliente_id' => $client->id,
            'fecha' => now()->toDateString(),
            'factura' => 'NE-VENTA-12',
            'nit_cliente' => '556677',
            'descuento_porcentaje' => 0,
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 2, 'precio_unitario' => 80, 'descuento' => 0, 'impuesto' => 0],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $sale = Venta::where('numero_venta', 'V-DOC-LOT')->firstOrFail();

        $nearest = InventarioLote::where('codigo_lote', 'LOTE-CERCANO')->firstOrFail();
        $later = InventarioLote::where('codigo_lote', 'LOTE-LEJANO')->firstOrFail();

        $this->assertSame('nota_entrega', $sale->tipo_documento);
        $this->assertSame('0.00', $nearest->cantidad_disponible);
        $this->assertSame('1.00', $later->cantidad_disponible);

        $this->get(route('ventas.index'))->assertOk()->assertDontSee('V-DOC-LOT');
        $this->get(route('reportes.ventas'))->assertOk()->assertDontSee('V-DOC-LOT');
        $this->get(route('reportes.documentos'))->assertOk()
            ->assertViewHas('notas', fn ($notas) => $notas->contains('id', $sale->id))
            ->assertViewHas('ventas', fn ($ventas) => ! $ventas->contains('id', $sale->id))
            ->assertSee(route('notas.show', $sale), false);
        $this->withSession(['commercial_context' => ['name' => 'Supra Medic', 'nit' => '10313888011', 'logo' => 'assets/logos/supra-medic.png']])
            ->get(route('dashboard'))->assertOk()
            ->assertViewHas('ventasHoy', 0)
            ->assertViewHas('totalHoy', fn ($total) => (float) $total === 0.0)
            ->assertViewHas('ultimasOperaciones', fn ($operaciones) => $operaciones->contains(fn ($item) => $item['numero'] === 'V-DOC-LOT' && $item['tipo'] === 'Nota de entrega'));
        $this->get(route('notas.show', $sale))->assertOk()->assertSee('Nota de entrega V-DOC-LOT');

        $this->actingAs($user)
            ->get(route('ventas.show', $sale))
            ->assertOk()
            ->assertSee('Nota de entrega')
            ->assertSee('LOTE-CERCANO');
    }

    public function test_sale_receipt_shows_batches_expiration_dates_and_pdf(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $client = Cliente::create(['nombre' => 'Cliente prueba lotes', 'documento' => '222333', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'REC-LOT',
            'nombre' => 'Reactivo prueba lote',
            'medida' => 'FRASCO',
            'precio_venta' => 127,
            'costo_referencial' => 100,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-REC-LOT',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'fecha' => now()->toDateString(),
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 4,
                    'costo_unitario' => 100,
                    'porcentaje_utilidad' => 27,
                    'codigo_lote' => 'REC-LOT-2026',
                    'fecha_vencimiento' => '2026-12-31',
                    'impuesto' => 0,
                ],
            ],
        ], $user->id);

        $sale = app(SaleService::class)->registrar([
            'numero_venta' => 'V-REC-LOT',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'nota_entrega',
            'cliente_id' => $client->id,
            'fecha' => now()->toDateString(),
            'factura' => 'NE-REC-LOT',
            'nit_cliente' => '222333',
            'descuento_porcentaje' => 0,
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 2, 'precio_unitario' => 127, 'descuento' => 0, 'impuesto' => 0],
            ],
        ], $user->id);

        $this->actingAs($user)
            ->get(route('ventas.show', $sale))
            ->assertOk()
            ->assertSee('Vista recibo')
            ->assertSee(route('ventas.recibo', $sale), false)
            ->assertDontSee('Vista prueba lotes')
            ->assertDontSee('PDF prueba lotes');

        $this->actingAs($user)
            ->get(route('ventas.recibo', $sale))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('ventas.pdf', $sale))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_annulling_sale_restores_consumed_batches(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'FEFO-ANULA',
            'nombre' => 'Producto anula lote',
            'precio_venta' => 100,
            'costo_referencial' => 50,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-FEFO-ANULA',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => now()->toDateString(),
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 2, 'costo_unitario' => 50, 'porcentaje_utilidad' => 27, 'fecha_vencimiento' => now()->addDays(5)->toDateString(), 'impuesto' => 0],
            ],
        ], $user->id);

        $sale = app(SaleService::class)->registrar([
            'numero_venta' => 'V-FEFO-ANULA',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => now()->toDateString(),
            'descuento_porcentaje' => 0,
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 2, 'precio_unitario' => 100, 'descuento' => 0, 'impuesto' => 0],
            ],
        ], $user->id);

        $this->assertSame('0.00', InventarioLote::first()->cantidad_disponible);

        app(SaleService::class)->anular($sale, $user->id);

        $this->assertSame('2.00', InventarioLote::first()->fresh()->cantidad_disponible);
        $this->assertSame(2.0, app(InventoryService::class)->stockActual($product));
    }

    public function test_product_edit_uses_current_exchange_rate_for_calculation(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'USD-EDIT',
            'nombre' => 'Producto dolar editable',
            'precio_ingreso_usd' => 10,
            'tipo_cambio' => 6.96,
            'porcentaje_utilidad' => 30,
            'precio_venta' => 90.48,
            'costo_referencial' => 69.60,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        TipoCambio::create([
            'fecha' => now()->toDateString(),
            'valor' => 11.50,
            'fuente' => 'BCB',
            'estado' => 'vigente',
            'consultado_en' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('productos.edit', $product))
            ->assertOk()
            ->assertSee('value="11.50"', false)
            ->assertSee('value="149.50"', false);
    }

    public function test_expiration_alert_uses_configured_days_on_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        ConfiguracionEmpresa::firstOrCreate([])->update(['dias_alerta_vencimiento' => 7]);
        $product = Producto::create([
            'codigo' => 'EXP-DASH',
            'nombre' => 'Producto alerta vencimiento',
            'precio_venta' => 10,
            'costo_referencial' => 5,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);
        InventarioLote::create([
            'producto_id' => $product->id,
            'fecha_vencimiento' => now()->addDays(6)->toDateString(),
            'cantidad_inicial' => 2,
            'cantidad_disponible' => 2,
            'costo_unitario' => 5,
            'estado' => 'activo',
            'usuario_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['commercial_context' => ['name' => 'San Benjamin', 'logo' => 'assets/logos/san-benjamin.jpeg', 'nit' => 'Pendiente']])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Vencimientos')
            ->assertSee('EXP-DASH')
            ->assertSee('7 días');
    }

    public function test_dashboard_opens_glass_expiration_alert_when_batches_are_near_expiration(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        ConfiguracionEmpresa::firstOrCreate([])->update(['dias_alerta_vencimiento' => 90]);
        $product = Producto::create([
            'codigo' => 'EXP-MODAL',
            'nombre' => 'Producto modal vencimiento',
            'precio_venta' => 10,
            'costo_referencial' => 5,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);
        InventarioLote::create([
            'producto_id' => $product->id,
            'codigo_lote' => 'LOT-MODAL',
            'fecha_vencimiento' => now()->addDays(30)->toDateString(),
            'cantidad_inicial' => 3,
            'cantidad_disponible' => 3,
            'costo_unitario' => 5,
            'estado' => 'activo',
            'usuario_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['commercial_context' => ['name' => 'San Benjamin', 'logo' => 'assets/logos/san-benjamin.jpeg', 'nit' => 'Pendiente']])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('glass-modal-backdrop is-open', false)
            ->assertSee('Vencimientos cercanos')
            ->assertSee('LOT-MODAL')
            ->assertSee('Producto modal vencimiento');
    }

    public function test_inventory_batch_edit_is_password_protected_and_respects_consumed_quantity(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true, 'password' => 'admin123']);
        $product = Producto::create([
            'codigo' => 'LOT-SEC',
            'nombre' => 'Producto lote seguro',
            'marca' => 'Marca visible',
            'procedencia' => 'Brasil',
            'precio_venta' => 20,
            'costo_referencial' => 10,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        $purchase = app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-LOT-SEC',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => now()->toDateString(),
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 5,
                    'costo_unitario' => 10,
                    'porcentaje_utilidad' => 27,
                    'codigo_lote' => 'LOT-OLD',
                    'fecha_vencimiento' => now()->addDays(40)->toDateString(),
                    'impuesto' => 0,
                ],
            ],
        ], $user->id);

        $sale = app(SaleService::class)->registrar([
            'numero_venta' => 'V-LOT-SEC',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => now()->toDateString(),
            'descuento_porcentaje' => 0,
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 2, 'precio_unitario' => 20, 'descuento' => 0, 'impuesto' => 0],
            ],
        ], $user->id);

        $lote = InventarioLote::where('producto_id', $product->id)->firstOrFail();

        $this->actingAs($user)
            ->get(route('inventario.lotes.index', ['producto_id' => $product->id]))
            ->assertOk()
            ->assertSee('Consumido')
            ->assertSee('Vencimiento')
            ->assertSee('Por vencer')
            ->assertSee('Marca visible')
            ->assertSee('Brasil')
            ->assertSee('2.00')
            ->assertSee('Máx. 3.00');

        $this->actingAs($user)
            ->get(route('compras.show', $purchase))
            ->assertOk()
            ->assertSee('Editar lote')
            ->assertSee('Marca visible')
            ->assertSee('Brasil')
            ->assertSee('/inventario/lotes?producto_id=' . $product->id . '&amp;estado=activo', false);

        $this->actingAs($user)
            ->get(route('ventas.show', $sale))
            ->assertOk()
            ->assertSee('Marca visible')
            ->assertSee('Brasil');

        $this->actingAs($user)
            ->put(route('inventario.lotes.update', $lote), [
                'force_password' => 'mala',
                'codigo_lote' => 'LOT-NEW',
                'fecha_vencimiento' => now()->addDays(20)->toDateString(),
                'cantidad_disponible' => 3,
                'estado' => 'activo',
            ])
            ->assertSessionHasErrors('force_password');

        $this->actingAs($user)
            ->put(route('inventario.lotes.update', $lote), [
                'force_password' => 'admin123',
                'codigo_lote' => 'LOT-NEW',
                'fecha_vencimiento' => now()->addDays(20)->toDateString(),
                'cantidad_disponible' => 4,
                'estado' => 'activo',
            ])
            ->assertSessionHasErrors('cantidad_disponible');

        $newDate = now()->addDays(20)->toDateString();

        $this->actingAs($user)
            ->put(route('inventario.lotes.update', $lote), [
                'force_password' => 'admin123',
                'codigo_lote' => 'LOT-NEW',
                'fecha_vencimiento' => $newDate,
                'cantidad_disponible' => 3,
                'estado' => 'activo',
                'observacion' => 'Lote corregido',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventario_lotes', [
            'id' => $lote->id,
            'codigo_lote' => 'LOT-NEW',
            'fecha_vencimiento' => $newDate . ' 00:00:00',
            'cantidad_disponible' => 3,
        ]);
        $this->assertDatabaseHas('auditoria_eventos', [
            'accion' => 'actualizo',
            'modulo' => 'lotes',
            'auditable_id' => $lote->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('detalle_compras', [
            'id' => $purchase->detalles->first()->id,
            'codigo_lote' => 'LOT-NEW',
            'fecha_vencimiento' => $newDate . ' 00:00:00',
        ]);
        $this->assertDatabaseHas('detalle_venta_lotes', [
            'detalle_venta_id' => $sale->detalles->first()->id,
            'inventario_lote_id' => $lote->id,
            'fecha_vencimiento' => $newDate,
        ]);
    }

    public function test_inventory_batches_can_be_filtered_by_expiration_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 09:00:00'));

        try {
            $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
            ConfiguracionEmpresa::firstOrCreate([])->update(['dias_alerta_vencimiento' => 30]);
            $product = Producto::create([
                'codigo' => 'LOT-FILTER',
                'nombre' => 'Producto filtro lote',
                'precio_venta' => 20,
                'costo_referencial' => 10,
                'stock_minimo' => 1,
                'activo' => true,
            ]);

            foreach ([
                ['codigo_lote' => 'LOTE-VENCIDO', 'fecha_vencimiento' => now()->subDay()->toDateString()],
                ['codigo_lote' => 'LOTE-POR-VENCER', 'fecha_vencimiento' => now()->addDays(10)->toDateString()],
                ['codigo_lote' => 'LOTE-VIGENTE', 'fecha_vencimiento' => now()->addDays(80)->toDateString()],
                ['codigo_lote' => 'LOTE-SIN-FECHA', 'fecha_vencimiento' => null],
            ] as $lote) {
                InventarioLote::create([
                    'producto_id' => $product->id,
                    'codigo_lote' => $lote['codigo_lote'],
                    'fecha_vencimiento' => $lote['fecha_vencimiento'],
                    'cantidad_inicial' => 1,
                    'cantidad_disponible' => 1,
                    'costo_unitario' => 10,
                    'estado' => 'activo',
                    'usuario_id' => $user->id,
                ]);
            }

            $this->actingAs($user)
                ->get(route('inventario.lotes.index', ['vencimiento' => 'por_vencer']))
                ->assertOk()
                ->assertSee('LOTE-POR-VENCER')
                ->assertDontSee('LOTE-VENCIDO')
                ->assertDontSee('LOTE-VIGENTE')
                ->assertDontSee('LOTE-SIN-FECHA');

            $this->actingAs($user)
                ->get(route('inventario.lotes.index', ['vencimiento' => 'vencidos']))
                ->assertOk()
                ->assertSee('LOTE-VENCIDO')
                ->assertDontSee('LOTE-POR-VENCER');

            $this->actingAs($user)
                ->get(route('inventario.lotes.index', ['vencimiento' => 'sin_fecha']))
                ->assertOk()
                ->assertSee('LOTE-SIN-FECHA')
                ->assertDontSee('LOTE-POR-VENCER');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_menu_respects_module_permissions(): void
    {
        $user = User::factory()->create([
            'role' => 'operador',
            'activo' => true,
            'permissions' => [
                'dashboard' => true,
                'ventas' => true,
                'compras' => false,
                'cotizaciones' => false,
                'inventario' => false,
                'productos' => false,
                'clientes' => false,
                'proveedores' => false,
                'reportes' => false,
            ],
        ]);

        $this->actingAs($user)
            ->withSession(['commercial_context' => ['name' => 'San Benjamin', 'logo' => 'assets/logos/san-benjamin.jpeg', 'nit' => 'Pendiente']])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Operación')
            ->assertSee('href="http://localhost/ventas"', false)
            ->assertDontSee('href="http://localhost/compras"', false)
            ->assertDontSee('href="http://localhost/compras/create"', false)
            ->assertDontSee('href="http://localhost/cotizaciones"', false)
            ->assertDontSee('href="http://localhost/inventario"', false)
            ->assertDontSee('href="http://localhost/reportes/balance-mensual"', false)
            ->assertDontSee('href="http://localhost/usuarios"', false);
    }

    public function test_internal_dark_mode_styles_cover_common_controls(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);

        $this->actingAs($user)
            ->withSession(['commercial_context' => ['name' => 'San Benjamin', 'logo' => 'assets/logos/san-benjamin.jpeg', 'nit' => 'Pendiente']])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('html.theme-dark .glass-dock .dock-menu', false)
            ->assertSee('html.theme-dark .notice', false)
            ->assertSee('html.theme-dark .errors', false)
            ->assertSee('html.theme-dark .force-delete', false)
            ->assertSee('html.theme-dark table a:not(.btn)', false);
    }

    public function test_kardex_and_low_stock_reports_can_generate_pdf(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'PDF-STOCK',
            'nombre' => 'Producto PDF stock',
            'precio_venta' => 10,
            'costo_referencial' => 5,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 5,
            'activo' => true,
        ]);

        app(InventoryService::class)->registrarMovimiento($product, 'entrada', 2, 'test', null, $user->id, 'Stock bajo PDF');

        $this->actingAs($user)
            ->get(route('reportes.stock-bajo.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('reportes.kardex.pdf', ['producto_id' => $product->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_kardex_shows_item_quantity_price_batch_and_reference_details(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $client = Cliente::create(['nombre' => 'Cliente kardex', 'documento' => '777', 'activo' => true]);
        $provider = Proveedor::create(['nombre' => 'Proveedor kardex', 'documento' => '888', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'KDX-ITEM',
            'nombre' => 'Producto kardex item',
            'precio_venta' => 126,
            'costo_referencial' => 100,
            'porcentaje_utilidad' => 26,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-KDX-1',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'proveedor_id' => $provider->id,
            'fecha' => now()->toDateString(),
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 5,
                    'costo_unitario' => 100,
                    'porcentaje_utilidad' => 26,
                    'codigo_lote' => 'KDX-LOT-1',
                    'fecha_vencimiento' => now()->addMonths(7)->toDateString(),
                    'impuesto' => 0,
                ],
            ],
        ], $user->id);

        app(SaleService::class)->registrar([
            'numero_venta' => 'V-KDX-1',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'cliente_id' => $client->id,
            'fecha' => now()->toDateString(),
            'descuento_porcentaje' => 0,
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 2, 'precio_unitario' => 126, 'descuento' => 0, 'impuesto' => 0],
            ],
        ], $user->id);

        $this->actingAs($user)
            ->get(route('reportes.kardex', ['producto_id' => $product->id]))
            ->assertOk()
            ->assertSee('Precio/Costo')
            ->assertSee('Total linea')
            ->assertSee('KDX-LOT-1')
            ->assertSee('C-KDX-1')
            ->assertSee('V-KDX-1')
            ->assertSee('Proveedor kardex')
            ->assertSee('Cliente kardex')
            ->assertSee('Bs 100.00')
            ->assertSee('Bs 126.00');

        $this->actingAs($user)
            ->get(route('reportes.kardex.pdf', ['producto_id' => $product->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_saved_reports_can_be_edited_and_deleted_with_password(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true, 'password' => 'admin123']);

        $this->actingAs($user)
            ->post(route('reportes.guardados.store'), [
                'titulo' => 'Reporte inicial',
                'tipo' => 'ventas',
                'ruta' => '/reportes/ventas?estado=registrada',
                'observacion' => 'Inicial',
            ])
            ->assertRedirect();

        $reporte = ReporteGuardado::firstOrFail();

        $this->actingAs($user)
            ->put(route('reportes.guardados.update', $reporte), [
                'titulo' => 'Reporte editado',
                'tipo' => 'inventario',
                'ruta' => '/reportes/inventario',
                'observacion' => 'Editado',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reportes_guardados', [
            'id' => $reporte->id,
            'titulo' => 'Reporte editado',
            'tipo' => 'inventario',
        ]);
        $this->assertDatabaseHas('auditoria_eventos', [
            'accion' => 'actualizo',
            'modulo' => 'reportes',
            'auditable_id' => $reporte->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('reportes.guardados.destroy', $reporte), ['force_password' => 'incorrecta'])
            ->assertSessionHasErrors('force_password');

        $this->assertDatabaseHas('reportes_guardados', ['id' => $reporte->id, 'activo' => true]);

        $this->actingAs($user)
            ->delete(route('reportes.guardados.destroy', $reporte), ['force_password' => 'admin123'])
            ->assertRedirect();

        $this->assertDatabaseHas('reportes_guardados', ['id' => $reporte->id, 'activo' => false]);
        $this->assertDatabaseHas('auditoria_eventos', [
            'accion' => 'elimino',
            'modulo' => 'reportes',
            'auditable_id' => $reporte->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_document_report_separates_invoices_and_delivery_notes_with_tax_reference(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $client = Cliente::create(['nombre' => 'Cliente documentos', 'documento' => '100200', 'activo' => true]);
        $provider = Proveedor::create(['nombre' => 'Proveedor documentos', 'documento' => '300400', 'activo' => true]);

        $ventaFactura = Venta::create([
            'numero_venta' => 'V-DOC-FAC',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'cliente_id' => $client->id,
            'fecha' => now()->toDateString(),
            'factura' => 'FV-1',
            'nit_cliente' => '100200',
            'subtotal' => 100,
            'descuento_porcentaje' => 0,
            'descuento' => 0,
            'impuesto_total' => 0,
            'total' => 100,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);

        $ventaNota = Venta::create([
            'numero_venta' => 'V-DOC-NOTA',
            'nombre_comercial' => 'Supra Medic',
            'tipo_documento' => 'nota_entrega',
            'cliente_id' => $client->id,
            'fecha' => now()->toDateString(),
            'factura' => 'NE-1',
            'nit_cliente' => '100200',
            'subtotal' => 50,
            'descuento_porcentaje' => 0,
            'descuento' => 0,
            'impuesto_total' => 0,
            'total' => 50,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);

        $compraFactura = Compra::create([
            'numero_compra' => 'C-DOC-FAC',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'proveedor_id' => $provider->id,
            'fecha' => now()->toDateString(),
            'factura' => 'FC-1',
            'nit_proveedor' => '300400',
            'subtotal' => 40,
            'impuesto_total' => 0,
            'total' => 40,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('reportes.documentos'))
            ->assertOk()
            ->assertSee('Notas y facturas')
            ->assertSee('V-DOC-FAC')
            ->assertSee('V-DOC-NOTA')
            ->assertSee('C-DOC-FAC')
            ->assertSee('Diferencia')
            ->assertSee('IVA 13%')
            ->assertSee('Bs -7.80')
            ->assertSee('IT 3%')
            ->assertSee('Bs 3.00')
            ->assertSee('Por pagar')
            ->assertSee('Bs -4.80')
            ->assertSee(route('ventas.recibo', $ventaFactura), false)
            ->assertSee(route('ventas.pdf', $ventaNota), false)
            ->assertSee(route('ventas.historial.edit', $ventaFactura), false)
            ->assertSee(route('compras.recibo', $compraFactura), false)
            ->assertSee(route('compras.pdf', $compraFactura), false)
            ->assertSee(route('compras.historial.edit', $compraFactura), false);

        $this->actingAs($user)
            ->get(route('reportes.documentos', ['tipo_documento' => 'factura']))
            ->assertOk()
            ->assertSee('V-DOC-FAC')
            ->assertDontSee('V-DOC-NOTA');

        $this->actingAs($user)
            ->post(route('reportes.guardados.store'), [
                'titulo' => 'Reporte documentos',
                'tipo' => 'documentos',
                'ruta' => '/reportes/documentos?tipo_documento=factura',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reportes_guardados', [
            'titulo' => 'Reporte documentos',
            'tipo' => 'documentos',
        ]);

        $this->actingAs($user)
            ->get(route('reportes.documentos.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_monthly_balance_separates_sales_purchases_invoices_notes_and_generates_pdf(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $client = Cliente::create(['nombre' => 'Cliente balance', 'documento' => '555111', 'activo' => true]);
        $provider = Proveedor::create(['nombre' => 'Proveedor balance', 'documento' => '777222', 'activo' => true]);

        Venta::create([
            'numero_venta' => 'V-BAL-FAC',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'cliente_id' => $client->id,
            'fecha' => '2026-09-10',
            'factura' => 'FV-BAL',
            'nit_cliente' => '555111',
            'subtotal' => 100,
            'descuento_porcentaje' => 0,
            'descuento' => 0,
            'impuesto_total' => 0,
            'total' => 100,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);

        Venta::create([
            'numero_venta' => 'V-BAL-NOTA',
            'nombre_comercial' => 'Supra Medic',
            'tipo_documento' => 'nota_entrega',
            'cliente_id' => $client->id,
            'fecha' => '2026-09-11',
            'factura' => 'NV-BAL',
            'nit_cliente' => '555111',
            'subtotal' => 80,
            'descuento_porcentaje' => 0,
            'descuento' => 0,
            'impuesto_total' => 0,
            'total' => 80,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);

        Compra::create([
            'numero_compra' => 'C-BAL-FAC',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'proveedor_id' => $provider->id,
            'fecha' => '2026-09-05',
            'factura' => 'FC-BAL',
            'nit_proveedor' => '777222',
            'subtotal' => 200,
            'impuesto_total' => 0,
            'total' => 200,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);

        Compra::create([
            'numero_compra' => 'C-BAL-NOTA',
            'nombre_comercial' => 'Supra Medic',
            'tipo_documento' => 'nota_entrega',
            'proveedor_id' => $provider->id,
            'fecha' => '2026-09-06',
            'factura' => 'NC-BAL',
            'nit_proveedor' => '777222',
            'subtotal' => 50,
            'impuesto_total' => 0,
            'total' => 50,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('reportes.balance-mensual', ['mes' => '2026-09']))
            ->assertOk()
            ->assertSee('Balance mensual')
            ->assertSee('San Benjamin')
            ->assertSee('Supra Medic')
            ->assertSee('Bs 100.00')
            ->assertSee('Bs 80.00')
            ->assertSee('Bs 200.00')
            ->assertSee('Bs 50.00')
            ->assertSee('Diferencia (compras - ventas)')
            ->assertSee('IVA 13%')
            ->assertSee('IT 3%')
            ->assertSee('Por pagar')
            ->assertSee('Bs 13.00')
            ->assertSee('Bs 3.00')
            ->assertSee('Bs 16.00');

        $this->actingAs($user)
            ->get(route('reportes.balance-mensual', ['mes' => '2026-09', 'nombre_comercial' => 'San Benjamin']))
            ->assertOk()
            ->assertSee('San Benjamin')
            ->assertDontSee('Bs 80.00')
            ->assertSee('Bs 16.00');

        $this->actingAs($user)
            ->get(route('reportes.balance-mensual.pdf', ['mes' => '2026-09']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_quotation_can_be_registered_without_moving_stock_and_render_pdf(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $client = Cliente::create(['nombre' => 'Cliente cotizacion', 'documento' => '445566', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'COT-001',
            'nombre' => 'Producto cotizado',
            'precio_venta' => 127,
            'costo_referencial' => 100,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);
        TipoCambio::create([
            'fecha' => now()->toDateString(),
            'valor' => 7.15,
            'fuente' => 'BCB',
            'estado' => 'vigente',
            'consultado_en' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('cotizaciones.create'))
            ->assertOk()
            ->assertSee('Tipo de cambio vigente: Bs 7.15')
            ->assertSee('cotizacion-items-alert');

        $cotizacion = app(QuotationService::class)->registrar([
            'numero_cotizacion' => 'CT-TEST-1',
            'nombre_comercial' => 'San Benjamin',
            'cliente_id' => $client->id,
            'fecha' => now()->toDateString(),
            'valida_hasta' => now()->addDays(10)->toDateString(),
            'nit_cliente' => '445566',
            'descuento_porcentaje' => 0,
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 2,
                    'porcentaje_utilidad' => 27,
                    'precio_unitario' => 127,
                    'descuento' => 0,
                    'impuesto' => 0,
                ],
            ],
        ], $user->id);

        $this->assertSame('254.00', $cotizacion->total);
        $this->assertSame(0.0, app(InventoryService::class)->stockActual($product));
        $this->assertDatabaseMissing('movimientos_inventario', ['producto_id' => $product->id]);

        $this->actingAs($user)
            ->get(route('cotizaciones.show', $cotizacion))
            ->assertOk()
            ->assertSee('CT-TEST-1')
            ->assertSee('Producto cotizado')
            ->assertSee('Documento destino')
            ->assertSee('Revisar stock');

        $cotizacion->update(['confirmada_at' => now(), 'confirmada_por_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('cotizaciones.pdf', $cotizacion))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_quotation_can_be_converted_to_sale_and_consumes_stock(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $client = Cliente::create(['nombre' => 'Cliente convertir cotizacion', 'documento' => '998877', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'COT-SALE',
            'nombre' => 'Producto cotizacion venta',
            'precio_venta' => 127,
            'costo_referencial' => 100,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-COT-STOCK',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'fecha' => now()->toDateString(),
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 5,
                    'costo_unitario' => 100,
                    'porcentaje_utilidad' => 27,
                    'codigo_lote' => 'COT-LOT-1',
                    'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
                    'impuesto' => 0,
                ],
            ],
        ], $user->id);

        $cotizacion = app(QuotationService::class)->registrar([
            'numero_cotizacion' => 'CT-TO-SALE',
            'nombre_comercial' => 'Supra Medic',
            'cliente_id' => $client->id,
            'fecha' => now()->toDateString(),
            'nit_cliente' => '998877',
            'descuento_porcentaje' => 0,
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 2,
                    'porcentaje_utilidad' => 27,
                    'precio_unitario' => 127,
                    'descuento' => 0,
                    'impuesto' => 0,
                ],
            ],
        ], $user->id);

        $this->actingAs($user)
            ->post(route('cotizaciones.convertir', $cotizacion))
            ->assertRedirect();

        $cotizacion->refresh();

        $this->assertSame('convertida', $cotizacion->estado);
        $this->assertNotNull($cotizacion->venta_id);
        $this->assertDatabaseHas('ventas', [
            'id' => $cotizacion->venta_id,
            'nombre_comercial' => 'Supra Medic',
            'tipo_documento' => 'factura',
            'lista_precios' => 'Cotizacion',
            'total' => 254,
        ]);
        $this->assertSame(3.0, app(InventoryService::class)->stockActual($product));
    }

    public function test_quotation_can_be_converted_to_delivery_note_with_custom_document_data(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $client = Cliente::create(['nombre' => 'Cliente nota cotizacion', 'documento' => '101010', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'COT-NOTE',
            'nombre' => 'Producto cotizacion nota',
            'precio_venta' => 150,
            'costo_referencial' => 120,
            'porcentaje_utilidad' => 25,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-COT-NOTE',
            'nombre_comercial' => 'Supra Medic',
            'tipo_documento' => 'factura',
            'fecha' => now()->toDateString(),
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 3,
                    'costo_unitario' => 120,
                    'porcentaje_utilidad' => 25,
                    'codigo_lote' => 'NOTE-LOT-1',
                    'fecha_vencimiento' => now()->addMonths(8)->toDateString(),
                    'impuesto' => 0,
                ],
            ],
        ], $user->id);

        $cotizacion = app(QuotationService::class)->registrar([
            'numero_cotizacion' => 'CT-TO-NOTE',
            'nombre_comercial' => 'San Benjamin',
            'cliente_id' => $client->id,
            'fecha' => now()->toDateString(),
            'nit_cliente' => '101010',
            'descuento_porcentaje' => 0,
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 1,
                    'porcentaje_utilidad' => 25,
                    'precio_unitario' => 150,
                    'descuento' => 0,
                    'impuesto' => 0,
                ],
            ],
        ], $user->id);

        $this->actingAs($user)
            ->post(route('cotizaciones.convertir', $cotizacion), [
                'tipo_documento' => 'nota_entrega',
                'fecha' => '2026-09-20',
                'factura' => 'NE-COT-001',
            ])
            ->assertRedirect();

        $cotizacion->refresh();

        $this->assertSame('convertida', $cotizacion->estado);
        $this->assertDatabaseHas('ventas', [
            'id' => $cotizacion->venta_id,
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'nota_entrega',
            'fecha' => '2026-09-20 00:00:00',
            'factura' => 'NE-COT-001',
            'lista_precios' => 'Cotizacion',
            'total' => 150,
        ]);
        $this->assertDatabaseHas('auditoria_eventos', [
            'accion' => 'convirtio',
            'modulo' => 'cotizaciones',
            'auditable_id' => $cotizacion->id,
        ]);
        $this->assertSame(2.0, app(InventoryService::class)->stockActual($product));
    }

    public function test_admin_can_manage_user_permissions_and_permissions_gate_modules(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'activo' => true, 'password' => 'admin123']);
        $operator = User::factory()->create([
            'role' => 'operador',
            'activo' => true,
            'permissions' => ['dashboard' => true, 'ventas' => false, 'compras' => true],
        ]);

        $this->actingAs($admin)
            ->get(route('usuarios.index'))
            ->assertOk()
            ->assertSee('Usuarios y permisos')
            ->assertSee('Ventas')
            ->assertSee('Compras')
            ->assertSee('Kardex')
            ->assertSee('Perfil de permisos')
            ->assertSee('Operador de ventas')
            ->assertSee('Administrador completo')
            ->assertDontSee('Email');

        $this->actingAs($admin)
            ->put(route('usuarios.update', $operator), [
                'name' => $operator->name,
                'username' => $operator->username,
                'role' => 'operador',
                'activo' => 1,
                'permissions' => ['dashboard', 'ventas'],
            ])
            ->assertRedirect();

        $operator->refresh();

        $this->assertTrue($operator->hasPermission('ventas'));
        $this->assertFalse($operator->hasPermission('compras'));

        $this->actingAs($operator)
            ->get(route('ventas.index'))
            ->assertOk();

        $this->actingAs($operator)
            ->get(route('compras.index'))
            ->assertForbidden();

        $this->actingAs($operator)
            ->get(route('usuarios.index'))
            ->assertForbidden();

        $this->actingAs($operator)
            ->withSession(['commercial_context' => ['name' => 'San Benjamin', 'logo' => 'assets/logos/san-benjamin.jpeg', 'nit' => 'Pendiente']])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Kardex');
    }

    public function test_admin_can_create_and_deactivate_user_safely(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $secret = $twoFactor->generateSecret();
        $twoFactor->confirmEnrollment($admin, $secret, app(Google2FA::class)->getCurrentOtp($secret));

        $this->actingAs($admin)
            ->post(route('usuarios.store'), [
                'name' => 'Operador pruebas',
                'username' => 'operador_pruebas',
                'password' => 'admin123',
                'role' => 'operador',
                'activo' => 1,
                'permissions' => ['dashboard', 'ventas'],
            ])
            ->assertRedirect();

        $operator = User::where('username', 'operador_pruebas')->firstOrFail();

        $this->assertTrue($operator->activo);
        $this->assertSame('operador_pruebas@sistema.local', $operator->email);
        $this->assertTrue($operator->hasPermission('ventas'));
        $this->assertFalse($operator->hasPermission('compras'));
        $createAudit = AuditEvent::where('modulo', 'usuarios')
            ->where('accion', 'creo')
            ->where('auditable_id', $operator->id)
            ->firstOrFail();
        $this->assertArrayNotHasKey('password', $createAudit->valores_nuevos ?? []);

        $this->actingAs($admin)
            ->delete(route('usuarios.destroy', $operator), [
                'totp_code' => app(Google2FA::class)->getCurrentOtp($secret),
            ])
            ->assertRedirect();

        $this->assertFalse($operator->fresh()->activo);
        $this->assertDatabaseHas('auditoria_eventos', [
            'accion' => 'desactivo',
            'modulo' => 'usuarios',
            'auditable_id' => $operator->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('usuarios.destroy', $admin))
            ->assertSessionHasErrors('usuario');
    }

    public function test_usernames_are_restricted_to_safe_internal_email_values(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'activo' => true]);

        $this->actingAs($admin)
            ->post(route('usuarios.store'), [
                'name' => 'Operador invalido',
                'username' => 'operador con espacios',
                'password' => 'admin123',
                'role' => 'operador',
                'activo' => 1,
                'permissions' => ['dashboard'],
            ])
            ->assertSessionHasErrors('username');

        $this->assertDatabaseMissing('users', ['username' => 'operador con espacios']);
    }

    public function test_system_keeps_at_least_one_active_admin_user(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'activo' => true]);

        $this->actingAs($admin)
            ->put(route('usuarios.update', $admin), [
                'name' => $admin->name,
                'username' => $admin->username,
                'role' => 'operador',
                'activo' => 0,
                'permissions' => ['dashboard'],
            ])
            ->assertRedirect();

        $admin->refresh();

        $this->assertSame('administrador', $admin->role);
        $this->assertTrue($admin->activo);
    }

    public function test_kardex_is_visible_as_a_report_module(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'activo' => true]);

        $this->actingAs($admin)
            ->withSession(['commercial_context' => ['name' => 'San Benjamin', 'logo' => 'assets/logos/san-benjamin.jpeg', 'nit' => 'Pendiente']])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Operación')
            ->assertSee('Inventario')
            ->assertSee('Reportes')
            ->assertSee('Administración')
            ->assertSee('Kardex');

        $this->actingAs($admin)
            ->get(route('reportes.kardex'))
            ->assertOk()
            ->assertSee('Kardex');
    }

    public function test_customer_history_link_is_only_visible_from_kardex(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        Cliente::create(['nombre' => 'Cliente visible', 'documento' => '123', 'activo' => true]);

        $this->actingAs($admin)
            ->get(route('ventas.index'))
            ->assertOk()
            ->assertDontSee('Historial por cliente')
            ->assertDontSee(route('clientes.historial'), false);

        $this->actingAs($admin)
            ->get(route('clientes.index'))
            ->assertOk()
            ->assertDontSee('Histórico')
            ->assertDontSee(route('clientes.historial'), false);

        $this->actingAs($admin)
            ->get(route('reportes.kardex'))
            ->assertOk()
            ->assertSee('Historial por cliente')
            ->assertSee(route('clientes.historial'), false);
    }

    public function test_sale_history_header_edit_requires_password_and_keeps_stock(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true, 'password' => 'admin123']);
        $cliente = Cliente::create(['nombre' => 'Cliente historial', 'documento' => '111', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'HIST-VTA',
            'nombre' => 'Producto historial venta',
            'precio_venta' => 20,
            'costo_referencial' => 10,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);
        app(InventoryService::class)->registrarMovimiento($product, 'entrada', 5, 'test', null, $user->id, 'Stock');
        $sale = app(SaleService::class)->registrar([
            'numero_venta' => 'V-HIST-EDIT',
            'tipo_documento' => 'nota_entrega',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'descuento_porcentaje' => 0,
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 1, 'precio_unitario' => 20, 'descuento' => 0, 'impuesto' => 0],
            ],
        ], $user->id);

        $this->actingAs($user)
            ->get(route('ventas.historial.edit', $sale))
            ->assertOk()
            ->assertSee('Vista previa')
            ->assertSee(route('ventas.recibo', $sale), false)
            ->assertSee(route('ventas.pdf', $sale), false)
            ->assertDontSee(route('ventas.recibo-lotes-prueba', $sale), false)
            ->assertDontSee(route('ventas.pdf-lotes-prueba', $sale), false);

        $this->actingAs($user)
            ->put(route('ventas.historial.update', $sale), [
                'force_password' => 'mala',
                'nombre_comercial' => 'Supra Medic',
                'fecha' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('force_password');

        $this->actingAs($user)
            ->put(route('ventas.historial.update', $sale), [
                'force_password' => 'admin123',
                'nombre_comercial' => 'Supra Medic',
                'cliente_id' => $cliente->id,
                'fecha' => now()->toDateString(),
                'factura' => 'F-EDIT',
                'tipo_documento' => 'factura',
                'nit_cliente' => '222',
                'lista_precios' => 'Precio de Venta',
                'observacion' => 'Cabecera editada',
            ])
            ->assertRedirect(route('ventas.show', $sale));

        $this->assertDatabaseHas('ventas', ['id' => $sale->id, 'nombre_comercial' => 'Supra Medic', 'factura' => 'F-EDIT', 'tipo_documento' => 'factura']);
        $this->assertSame(4.0, app(InventoryService::class)->stockActual($product));
        $this->assertDatabaseHas('auditoria_eventos', [
            'accion' => 'actualizo',
            'modulo' => 'ventas',
            'auditable_id' => $sale->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_document_conversion_rolls_back_if_audit_fails(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true, 'password' => 'admin123']);
        $note = Venta::create([
            'numero_venta' => 'NE-AUDIT-FAIL',
            'tipo_documento' => 'nota_entrega',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => today(),
            'usuario_id' => $user->id,
            'estado' => 'registrada',
            'total' => 100,
        ]);
        $this->mock(\App\Services\AuditLogService::class, function ($mock) {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Fallo de auditoría simulado'));
        });

        $this->actingAs($user)->put(route('ventas.historial.update', $note), [
            'force_password' => 'admin123',
            'fecha' => today()->toDateString(),
            'tipo_documento' => 'factura',
            'factura' => 'F-NO-GUARDADA',
        ])->assertStatus(500);

        $this->assertSame('nota_entrega', $note->fresh()->tipo_documento);
        $this->assertNull($note->fresh()->factura);
        $this->assertSame('100.00', $note->fresh()->total);
        $this->assertDatabaseCount('auditoria_eventos', 0);
    }

    public function test_purchase_history_header_edit_requires_password_and_keeps_stock(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true, 'password' => 'admin123']);
        $proveedor = Proveedor::create(['nombre' => 'Proveedor historial', 'documento' => '999', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'HIST-CMP',
            'nombre' => 'Producto historial compra',
            'precio_venta' => 0,
            'costo_referencial' => 0,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ]);
        $purchase = app(PurchaseService::class)->registrar([
            'numero_compra' => 'C-HIST-EDIT',
            'nombre_comercial' => 'San Benjamin',
            'fecha' => now()->toDateString(),
            'proveedor_id' => $proveedor->id,
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 3, 'costo_unitario' => 20, 'porcentaje_utilidad' => 27, 'impuesto' => 0],
            ],
        ], $user->id);

        $this->actingAs($user)
            ->get(route('compras.historial.edit', $purchase))
            ->assertOk()
            ->assertSee('Vista previa')
            ->assertSee(route('compras.recibo', $purchase), false)
            ->assertSee(route('compras.pdf', $purchase), false);

        $this->actingAs($user)
            ->put(route('compras.historial.update', $purchase), [
                'force_password' => 'mala',
                'nombre_comercial' => 'Supra Medic',
                'fecha' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('force_password');

        $this->actingAs($user)
            ->put(route('compras.historial.update', $purchase), [
                'force_password' => 'admin123',
                'nombre_comercial' => 'Supra Medic',
                'proveedor_id' => $proveedor->id,
                'fecha' => now()->toDateString(),
                'factura' => 'FC-EDIT',
                'nit_proveedor' => '12345',
                'observacion' => 'Cabecera compra editada',
            ])
            ->assertRedirect(route('compras.show', $purchase));

        $this->assertDatabaseHas('compras', ['id' => $purchase->id, 'nombre_comercial' => 'Supra Medic', 'factura' => 'FC-EDIT']);
        $this->assertSame(3.0, app(InventoryService::class)->stockActual($product));
        $this->assertDatabaseHas('auditoria_eventos', [
            'accion' => 'actualizo',
            'modulo' => 'compras',
            'auditable_id' => $purchase->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_sprint_two_full_purchase_sale_receipts_kardex_and_balance_flow(): void
    {
        $this->travelTo(Carbon::parse('2026-09-12 10:00:00'));
        Http::preventStrayRequests();
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $this->actingAs($user)->withSession(['commercial_context' => [
            'name' => 'San Benjamin',
            'logo' => 'assets/logos/san-benjamin.png',
            'nit' => '411168020',
        ]]);
        ConfiguracionEmpresa::firstOrCreate([])->update(['dias_alerta_vencimiento' => 120]);
        $client = Cliente::create(['nombre' => 'Cliente flujo sprint 2', 'documento' => '900100', 'activo' => true]);
        $provider = Proveedor::create(['nombre' => 'Proveedor flujo sprint 2', 'documento' => '800200', 'activo' => true]);
        $this->post(route('productos.store'), [
            'codigo' => 'FLOW-S2',
            'nombre' => 'Producto flujo sprint 2',
            'marca' => 'Marca de prueba', 'procedencia' => 'Bolivia',
            'medida' => 'UNIDAD',
            'precio_ingreso_usd' => 10,
            'tipo_cambio' => 10,
            'precio_venta' => 0,
            'costo_referencial' => 0,
            'porcentaje_utilidad' => 27,
            'stock_minimo' => 1,
            'activo' => true,
        ])->assertSessionHasNoErrors()->assertRedirect(route('productos.index'));
        $product = Producto::where('codigo', 'FLOW-S2')->firstOrFail();

        $this->post(route('compras.store'), [
            'numero_compra' => 'C-FLOW-S2-A',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'proveedor_id' => $provider->id,
            'fecha' => '2026-09-03',
            'factura' => 'FC-FLOW-A',
            'nit_proveedor' => '800200',
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 2,
                    'costo_unitario' => 100,
                    'porcentaje_utilidad' => 27,
                    'codigo_lote' => 'FLOW-LEJANO',
                    'fecha_vencimiento' => '2027-12-31',
                    'impuesto' => 0,
                ],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $purchaseA = Compra::where('numero_compra', 'C-FLOW-S2-A')->firstOrFail();

        $this->post(route('compras.store'), [
            'numero_compra' => 'C-FLOW-S2-B',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'proveedor_id' => $provider->id,
            'fecha' => '2026-09-04',
            'factura' => 'FC-FLOW-B',
            'nit_proveedor' => '800200',
            'items' => [
                [
                    'producto_id' => $product->id,
                    'cantidad' => 3,
                    'costo_unitario' => 100,
                    'porcentaje_utilidad' => 27,
                    'codigo_lote' => 'FLOW-CERCANO',
                    'fecha_vencimiento' => '2026-12-31',
                    'impuesto' => 0,
                ],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $purchaseB = Compra::where('numero_compra', 'C-FLOW-S2-B')->firstOrFail();
        $this->assertSame(5.0, app(InventoryService::class)->stockActual($product));
        $this->assertSame('127.00', $product->fresh()->precio_venta);
        $this->get(route('productos.show', $product))->assertOk()
            ->assertSee('FLOW-CERCANO')->assertSee('FLOW-LEJANO');
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('glass-modal-backdrop is-open', false)
            ->assertViewHas('lotesPorVencer', fn ($lotes) => $lotes->pluck('codigo_lote')->all() === ['FLOW-CERCANO']);

        $this->post(route('ventas.store'), [
            'numero_venta' => 'V-FLOW-S2',
            'nombre_comercial' => 'Supra Medic',
            'tipo_documento' => 'factura',
            'cliente_id' => $client->id,
            'fecha' => '2026-09-12',
            'factura' => 'FV-FLOW',
            'nit_cliente' => '900100',
            'descuento_porcentaje' => 0,
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 4, 'precio_unitario' => 127, 'descuento' => 0, 'impuesto' => 0],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $sale = Venta::where('numero_venta', 'V-FLOW-S2')->firstOrFail();

        $nearBatch = InventarioLote::where('codigo_lote', 'FLOW-CERCANO')->firstOrFail();
        $farBatch = InventarioLote::where('codigo_lote', 'FLOW-LEJANO')->firstOrFail();

        $this->assertSame('0.00', $nearBatch->cantidad_disponible);
        $this->assertSame('1.00', $farBatch->cantidad_disponible);
        $this->assertSame(1.0, app(InventoryService::class)->stockActual($product));
        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('lotesPorVencer', fn ($lotes) => $lotes->isEmpty())
            ->assertViewHas('productosBajoStock', fn ($productos) => $productos->contains('id', $product->id));

        $this->post(route('ventas.store'), [
            'numero_venta' => 'V-FLOW-S2-SIN-STOCK',
            'nombre_comercial' => 'Supra Medic',
            'tipo_documento' => 'factura',
            'fecha' => '2026-09-12',
            'items' => [
                ['producto_id' => $product->id, 'cantidad' => 2, 'precio_unitario' => 127],
            ],
        ])->assertSessionHasErrors('venta');
        $this->assertDatabaseMissing('ventas', ['numero_venta' => 'V-FLOW-S2-SIN-STOCK']);
        $this->assertSame(1.0, app(InventoryService::class)->stockActual($product));
        $this->assertSame('1.00', $farBatch->fresh()->cantidad_disponible);

        foreach (['compras.recibo' => $purchaseB, 'ventas.recibo' => $sale] as $route => $document) {
            $preview = $this->get(route($route, $document))->assertOk()
                ->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $preview->getContent());
        }

        $this->actingAs($user)
            ->get(route('compras.show', $purchaseA))
            ->assertOk()
            ->assertSee('FLOW-LEJANO')
            ->assertSee('31/12/2027');

        $this->actingAs($user)
            ->get(route('ventas.show', $sale))
            ->assertOk()
            ->assertSee('FLOW-CERCANO')
            ->assertSee('FLOW-LEJANO')
            ->assertSee('Cliente flujo sprint 2');

        $this->actingAs($user)
            ->get(route('compras.historial.edit', $purchaseB))
            ->assertOk()
            ->assertSee(route('compras.recibo', $purchaseB), false);

        $this->actingAs($user)
            ->get(route('ventas.historial.edit', $sale))
            ->assertOk()
            ->assertSee(route('ventas.recibo', $sale), false);

        $this->actingAs($user)
            ->get(route('compras.pdf', $purchaseB))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('ventas.pdf', $sale))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('reportes.kardex', ['producto_id' => $product->id]))
            ->assertOk()
            ->assertSee('FLOW-CERCANO')
            ->assertSee('FLOW-LEJANO')
            ->assertSee('V-FLOW-S2');

        $this->actingAs($user)
            ->get(route('reportes.balance-mensual', ['mes' => '2026-09']))
            ->assertOk()
            ->assertSee('Bs 508.00')
            ->assertSee('Bs 500.00');
    }

    public function test_dashboard_options_can_be_configured_per_role(): void
    {
        ConfiguracionEmpresa::firstOrCreate([])->update([
            'dashboard_opciones_por_rol' => [
                'operador' => [
                    'ventas_hoy' => false,
                    'movimiento_mensual' => false,
                    'ultimas_operaciones' => false,
                    'facturacion_mensual' => false,
                ],
                'administrador' => [
                    'ventas_hoy' => true,
                    'movimiento_mensual' => true,
                    'ultimas_operaciones' => true,
                    'facturacion_mensual' => true,
                ],
            ],
        ]);

        $operador = User::factory()->create([
            'role' => 'operador',
            'activo' => true,
            'permissions' => ['dashboard' => true],
        ]);
        $administrador = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $session = ['commercial_context' => ['name' => 'San Benjamin', 'logo' => 'assets/logos/san-benjamin.jpeg', 'nit' => 'Pendiente']];

        $this->actingAs($administrador)
            ->get(route('configuracion.edit'))
            ->assertOk()
            ->assertSee('Administradores')
            ->assertSee('Operadores');

        $this->put(route('configuracion.update'), [
            'nombre_negocio' => 'San Benjamin',
            'moneda' => 'Bs',
            'tamano_papel_recibo' => 'letter',
            'dias_alerta_vencimiento' => 30,
            'dias_alerta_cuotas' => 10,
            'dashboard_opciones_por_rol' => [
                'administrador' => [
                    'ventas_hoy' => true,
                    'movimiento_mensual' => true,
                    'ultimas_operaciones' => true,
                    'facturacion_mensual' => true,
                ],
                'operador' => [
                    'ventas_hoy' => false,
                    'movimiento_mensual' => false,
                    'ultimas_operaciones' => false,
                    'facturacion_mensual' => false,
                ],
            ],
        ])->assertRedirect();
        $this->assertFalse((bool) ConfiguracionEmpresa::first()->fresh()->dashboard_opciones_por_rol['operador']['ventas_hoy']);

        $this->actingAs($operador)
            ->withSession($session)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Ventas del dia')
            ->assertDontSee('Movimiento mensual')
            ->assertDontSee('Ultimas operaciones');

        $this->actingAs($administrador)
            ->withSession($session)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ventas del dia')
            ->assertSee('Movimiento mensual')
            ->assertSee('Ultimas operaciones');
    }
}


