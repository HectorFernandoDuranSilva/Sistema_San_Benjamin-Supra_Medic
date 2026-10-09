<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportExcelController;
use App\Http\Controllers\InventoryBatchController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::view('/design-options', 'design-options.index')->name('design-options.index');
Route::get('/design-options/{style}/{screen}', function (string $style, string $screen) {
    abort_unless(in_array($style, ['violet-lab', 'botanical-clean', 'blue-depth', 'commerce-minimal', 'clinic-premium', 'operator-pos', 'erp-modern', 'glass-clear'], true), 404);
    abort_unless(in_array($screen, ['login', 'dashboard'], true), 404);

    return view('design-options.show', compact('style', 'screen'));
})->name('design-options.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::middleware('permission:seguridad')->group(function () {
        Route::get('/seguridad/autenticador', [SecurityController::class, 'show'])->name('seguridad.authenticator');
        Route::post('/seguridad/autenticador/iniciar', [SecurityController::class, 'begin'])->name('seguridad.authenticator.iniciar');
        Route::post('/seguridad/autenticador/confirmar', [SecurityController::class, 'confirm'])->name('seguridad.authenticator.confirmar');
        Route::post('/seguridad/autenticador/reiniciar', [SecurityController::class, 'restart'])->name('seguridad.authenticator.reiniciar');
        Route::get('/seguridad/dashboard', [SecurityController::class, 'dashboard'])->name('seguridad.dashboard');
        Route::put('/seguridad/dashboard/{usuario}', [SecurityController::class, 'updateDashboard'])->name('seguridad.dashboard.update');
    });

    Route::get('/historico-clientes', [\App\Http\Controllers\CustomerHistoryController::class, 'index'])->middleware('permission:reportes,historial_clientes')->name('clientes.historial');
    Route::get('/historico-clientes/pdf', [\App\Http\Controllers\CustomerHistoryController::class, 'pdf'])->middleware('permission:reportes,historial_clientes')->name('clientes.historial.pdf');
    Route::post('/ventas/{venta}/cobros', [\App\Http\Controllers\CustomerHistoryController::class, 'storePayment'])->middleware('permission:ventas')->name('ventas.cobros.store');
    Route::post('/cobros/{cobro}/anular', [\App\Http\Controllers\CustomerHistoryController::class, 'cancelPayment'])->middleware('permission:ventas')->name('cobros.anular');
    Route::get('/seleccionar-nombre-comercial', [AuthController::class, 'showCommercialSelection'])->name('commercial.select');
    Route::post('/seleccionar-nombre-comercial', [AuthController::class, 'selectCommercial'])->name('commercial.select.store');

    Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard')->name('dashboard');

    Route::delete('/productos/{producto}/forzar', [ProductController::class, 'forceDestroy'])->middleware('permission:productos')->name('productos.force-destroy');
    Route::resource('productos', ProductController::class)->except(['show'])->middleware('permission:productos');
    Route::post('/productos/{producto}/aprobar-precio', [ProductController::class, 'approvePrice'])->middleware('permission:productos')->name('productos.precio.aprobar');
    Route::post('/productos/{producto}/lotes', [ProductController::class, 'storeBatch'])->middleware('permission:productos')->name('productos.lotes.store');
    Route::get('/productos/{producto}', [ProductController::class, 'show'])->middleware('permission:productos')->name('productos.show');
    Route::resource('categorias', CategoryController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:productos');
    Route::resource('clientes', CustomerController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:clientes');
    Route::resource('proveedores', SupplierController::class)->only(['index', 'store', 'update'])->middleware('permission:proveedores')->parameters(['proveedores' => 'proveedor']);

    Route::get('/inventario', [InventoryController::class, 'index'])->middleware('permission:inventario')->name('inventario.index');
    Route::get('/inventario/movimientos', [InventoryController::class, 'movimientos'])->middleware('permission:inventario')->name('inventario.movimientos');
    Route::post('/inventario/ajustar', [InventoryController::class, 'ajustar'])->middleware('permission:inventario')->name('inventario.ajustar');
    Route::get('/inventario/lotes', [InventoryBatchController::class, 'index'])->middleware('permission:inventario')->name('inventario.lotes.index');
    Route::put('/inventario/lotes/{lote}', [InventoryBatchController::class, 'update'])->middleware('permission:inventario')->name('inventario.lotes.update');
    Route::delete('/inventario/lotes/{lote}', [InventoryBatchController::class, 'destroy'])->middleware('permission:inventario')->name('inventario.lotes.destroy');

    Route::resource('ventas', SaleController::class)->only(['index', 'create', 'store', 'show'])->middleware('permission:ventas');
    Route::get('/notas-facturas/notas/crear', [SaleController::class, 'create'])->middleware('permission:ventas')->name('notas.create');
    Route::post('/notas-facturas/notas', [SaleController::class, 'store'])->middleware('permission:ventas')->name('notas.store');
    Route::get('/notas-facturas/notas/{venta}', [SaleController::class, 'show'])->middleware('permission:ventas')->name('notas.show');
    Route::get('/ventas/{venta}/editar-historial', [SaleController::class, 'editHistory'])->middleware('permission:ventas')->name('ventas.historial.edit');
    Route::put('/ventas/{venta}/editar-historial', [SaleController::class, 'updateHistory'])->middleware('permission:ventas')->name('ventas.historial.update');
    Route::post('/ventas/{venta}/anular', [SaleController::class, 'anular'])->middleware('permission:ventas')->name('ventas.anular');
    Route::get('/ventas/{venta}/recibo', [SaleController::class, 'recibo'])->middleware('permission:ventas')->name('ventas.recibo');
    Route::get('/ventas/{venta}/pdf', [SaleController::class, 'pdf'])->middleware('permission:ventas')->name('ventas.pdf');
    Route::get('/ventas/{venta}/recibo-prueba-lotes', [SaleController::class, 'reciboLotesPrueba'])->middleware('permission:ventas')->name('ventas.recibo-lotes-prueba');
    Route::get('/ventas/{venta}/pdf-prueba-lotes', [SaleController::class, 'pdfLotesPrueba'])->middleware('permission:ventas')->name('ventas.pdf-lotes-prueba');

    Route::resource('compras', PurchaseController::class)->only(['index', 'create', 'store', 'show'])->middleware('permission:compras');
    Route::post('/compras/{compra}/pagos', [PurchaseController::class, 'storePayment'])->middleware('permission:compras')->name('compras.pagos.store');
    Route::post('/pagos-compras/{pago}/anular', [PurchaseController::class, 'cancelPayment'])->middleware('permission:compras')->name('pagos-compras.anular');
    Route::get('/compras/{compra}/editar-historial', [PurchaseController::class, 'editHistory'])->middleware('permission:compras')->name('compras.historial.edit');
    Route::put('/compras/{compra}/editar-historial', [PurchaseController::class, 'updateHistory'])->middleware('permission:compras')->name('compras.historial.update');
    Route::get('/compras/{compra}/recibo', [PurchaseController::class, 'recibo'])->middleware('permission:compras')->name('compras.recibo');
    Route::get('/compras/{compra}/pdf', [PurchaseController::class, 'pdf'])->middleware('permission:compras')->name('compras.pdf');

    Route::resource('cotizaciones', QuotationController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update'])->middleware('permission:cotizaciones')->parameters(['cotizaciones' => 'cotizacion']);
    Route::post('/cotizaciones/{cotizacion}/convertir', [QuotationController::class, 'convertir'])->middleware('permission:cotizaciones')->name('cotizaciones.convertir');
    Route::post('/cotizaciones/{cotizacion}/confirmar', [QuotationController::class, 'confirm'])->middleware('permission:cotizaciones')->name('cotizaciones.confirm');
    Route::get('/cotizaciones/{cotizacion}/pdf', [QuotationController::class, 'pdf'])->middleware('permission:cotizaciones')->name('cotizaciones.pdf');
    Route::get('/cotizaciones/{cotizacion}/word', [QuotationController::class, 'word'])->middleware('permission:cotizaciones')->name('cotizaciones.word');

    Route::middleware('permission:reportes')->group(function () {
        Route::get('/reportes/ventas', [ReportController::class, 'ventas'])->name('reportes.ventas');
        Route::get('/reportes/documentos', [ReportController::class, 'documentos'])->middleware('permission:notas_facturas')->name('reportes.documentos');
        Route::get('/reportes/documentos/pdf', [ReportController::class, 'documentosPdf'])->middleware('permission:notas_facturas')->name('reportes.documentos.pdf');
        Route::get('/reportes/documentos/excel', [ReportController::class, 'documentosExcel'])->middleware('permission:notas_facturas')->name('reportes.documentos.excel');
        Route::get('/reportes/balance-mensual', [ReportController::class, 'balanceMensual'])->middleware('permission:balance_mensual')->name('reportes.balance-mensual');
        Route::get('/reportes/balance-mensual/pdf', [ReportController::class, 'balanceMensualPdf'])->middleware('permission:balance_mensual')->name('reportes.balance-mensual.pdf');
        Route::get('/reportes/balance-mensual/excel', [ReportController::class, 'balanceMensualExcel'])->middleware('permission:balance_mensual')->name('reportes.balance-mensual.excel');
        Route::get('/reportes/guardados', [ReportController::class, 'guardados'])->name('reportes.guardados');
        Route::post('/reportes/guardados', [ReportController::class, 'guardarActual'])->name('reportes.guardados.store');
        Route::put('/reportes/guardados/{reporte}', [ReportController::class, 'actualizarGuardado'])->name('reportes.guardados.update');
        Route::delete('/reportes/guardados/{reporte}', [ReportController::class, 'eliminarGuardado'])->name('reportes.guardados.destroy');
        Route::get('/reportes/inventario', [ReportController::class, 'inventario'])->name('reportes.inventario');
        Route::get('/reportes/stock-bajo/pdf', [ReportController::class, 'stockBajoPdf'])->name('reportes.stock-bajo.pdf');
        Route::get('/reportes/kardex', [ReportController::class, 'kardex'])->middleware('permission:kardex')->name('reportes.kardex');
        Route::get('/reportes/kardex/pdf', [ReportController::class, 'kardexPdf'])->middleware('permission:kardex')->name('reportes.kardex.pdf');
        Route::get('/reportes/compras', [ReportController::class, 'compras'])->name('reportes.compras');
    });

    Route::get('/configuracion', [SettingsController::class, 'edit'])->middleware('permission:configuracion')->name('configuracion.edit');
    Route::put('/configuracion', [SettingsController::class, 'update'])->middleware('permission:configuracion')->name('configuracion.update');

    Route::resource('usuarios', UserManagementController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:usuarios');

    Route::get('/importar/excel', [ImportExcelController::class, 'index'])->middleware('permission:importar')->name('importar.excel');
    Route::post('/importar/excel/preview', [ImportExcelController::class, 'preview'])->middleware(['permission:importar', 'throttle:excel-import'])->name('importar.preview');
    Route::post('/importar/excel/confirmar', [ImportExcelController::class, 'confirm'])->middleware('permission:importar')->name('importar.confirm');
});
