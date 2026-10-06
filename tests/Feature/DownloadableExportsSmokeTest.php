<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Cotizacion;
use App\Models\DetalleCompra;
use App\Models\DetalleCotizacion;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DownloadableExportsSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_downloadable_export_generates_without_server_error(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $product = Producto::create([
            'codigo' => 'EXP-001',
            'nombre' => 'Producto para exportaciones',
            'medida' => 'Unidad',
            'marca' => 'Marca QA',
            'procedencia' => 'Bolivia',
            'moneda_costo' => 'BOB',
            'precio_ingreso_usd' => 0,
            'tipo_cambio' => 6.96,
            'porcentaje_utilidad' => 25,
            'precio_venta' => 125,
            'costo_referencial' => 100,
            'stock_minimo' => 0,
            'activo' => true,
        ]);
        $customer = Cliente::create(['nombre' => 'Cliente de exportaciones', 'documento' => '1234567', 'activo' => true]);
        $supplier = Proveedor::create(['nombre' => 'Proveedor de exportaciones', 'documento' => '7654321', 'activo' => true]);

        $sale = Venta::create([
            'numero_venta' => 'V-EXP-001',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'cliente_id' => $customer->id,
            'fecha' => '2026-10-05',
            'factura' => 'EXP-001',
            'nit_cliente' => $customer->documento,
            'lista_precios' => 'venta',
            'subtotal' => 125,
            'descuento_porcentaje' => 0,
            'descuento' => 0,
            'impuesto_total' => 0,
            'costo_envio_total' => 0,
            'otros_costos_total' => 0,
            'total' => 125,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);
        DetalleVenta::create([
            'venta_id' => $sale->id,
            'producto_id' => $product->id,
            'codigo_producto' => $product->codigo,
            'nombre_producto' => $product->nombre,
            'cantidad' => 1,
            'precio_unitario' => 125,
            'subtotal' => 125,
            'descuento' => 0,
            'impuesto' => 0,
            'costo_envio' => 0,
            'otros_costos' => 0,
            'total' => 125,
        ]);

        $purchase = Compra::create([
            'numero_compra' => 'C-EXP-001',
            'nombre_comercial' => 'San Benjamin',
            'tipo_documento' => 'factura',
            'proveedor_id' => $supplier->id,
            'fecha' => '2026-10-05',
            'factura' => 'PC-EXP-001',
            'nit_proveedor' => $supplier->documento,
            'subtotal' => 100,
            'impuesto_total' => 0,
            'total' => 100,
            'estado' => 'registrada',
            'usuario_id' => $user->id,
        ]);
        DetalleCompra::create([
            'compra_id' => $purchase->id,
            'producto_id' => $product->id,
            'codigo_producto' => $product->codigo,
            'nombre_producto' => $product->nombre,
            'cantidad' => 1,
            'costo_unitario' => 100,
            'porcentaje_utilidad' => 25,
            'subtotal' => 100,
            'impuesto' => 0,
            'total' => 100,
        ]);

        $quotation = Cotizacion::create([
            'numero_cotizacion' => 'CT-EXP-001',
            'nombre_comercial' => 'San Benjamin',
            'cliente_id' => $customer->id,
            'fecha' => '2026-10-05',
            'valida_hasta' => '2026-10-15',
            'nit_cliente' => $customer->documento,
            'subtotal' => 125,
            'descuento_porcentaje' => 0,
            'descuento' => 0,
            'impuesto_total' => 0,
            'total' => 125,
            'estado' => 'registrada',
            'confirmada_at' => now(),
            'confirmada_por_id' => $user->id,
            'usuario_id' => $user->id,
        ]);
        DetalleCotizacion::create([
            'cotizacion_id' => $quotation->id,
            'producto_id' => $product->id,
            'codigo_producto' => $product->codigo,
            'nombre_producto' => $product->nombre,
            'medida' => $product->medida,
            'marca' => $product->marca,
            'procedencia' => $product->procedencia,
            'cantidad' => 1,
            'costo_referencial' => 100,
            'porcentaje_utilidad' => 25,
            'precio_unitario' => 125,
            'subtotal' => 125,
            'descuento' => 0,
            'impuesto' => 0,
            'total' => 125,
        ]);

        $session = ['commercial_context' => [
            'key' => 'san-benjamin',
            'name' => 'San Benjamin',
            'nit' => '411168020',
            'logo' => 'assets/logos/san-benjamin.png',
        ]];
        $downloads = [
            route('compras.pdf', $purchase) => 'application/pdf',
            route('compras.recibo', $purchase) => 'application/pdf',
            route('cotizaciones.pdf', $quotation) => 'application/pdf',
            route('cotizaciones.word', $quotation) => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            route('clientes.historial.pdf', ['cliente_id' => $customer->id]) => 'application/pdf',
            route('reportes.balance-mensual.excel', ['mes' => '2026-10']) => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            route('reportes.balance-mensual.pdf', ['mes' => '2026-10']) => 'application/pdf',
            route('reportes.documentos.excel', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']) => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            route('reportes.documentos.pdf', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']) => 'application/pdf',
            route('reportes.kardex.pdf', ['producto_id' => $product->id]) => 'application/pdf',
            route('reportes.stock-bajo.pdf') => 'application/pdf',
            route('ventas.pdf', $sale) => 'application/pdf',
            route('ventas.pdf-lotes-prueba', $sale) => 'application/pdf',
            route('ventas.recibo', $sale) => 'application/pdf',
            route('ventas.recibo-lotes-prueba', $sale) => 'application/pdf',
        ];

        foreach ($downloads as $route => $contentType) {
            $this->actingAs($user)
                ->withSession($session)
                ->get($route)
                ->assertOk()
                ->assertHeader('content-type', $contentType);
        }
    }
}
