<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('documento')->nullable()->index();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('documento')->nullable()->index();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->text('descripcion')->nullable();
            $table->string('medida')->nullable();
            $table->string('ubicacion')->nullable();
            $table->decimal('precio_venta', 14, 2)->default(0);
            $table->decimal('costo_referencial', 14, 2)->default(0);
            $table->decimal('stock_minimo', 14, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->string('numero_venta')->unique();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('descuento', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('estado')->default('registrada');
            $table->text('observacion')->nullable();
            $table->foreignId('usuario_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('detalle_ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->string('codigo_producto');
            $table->string('nombre_producto');
            $table->decimal('cantidad', 14, 2);
            $table->decimal('precio_unitario', 14, 2);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();
        });

        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('numero_compra')->unique();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('estado')->default('registrada');
            $table->text('observacion')->nullable();
            $table->foreignId('usuario_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('detalle_compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->string('codigo_producto');
            $table->string('nombre_producto');
            $table->decimal('cantidad', 14, 2);
            $table->decimal('costo_unitario', 14, 2);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();
        });

        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos');
            $table->string('tipo_movimiento');
            $table->decimal('cantidad', 14, 2);
            $table->decimal('stock_anterior', 14, 2);
            $table->decimal('stock_nuevo', 14, 2);
            $table->string('referencia_tipo')->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_movimiento');
            $table->timestamps();
            $table->index(['referencia_tipo', 'referencia_id']);
        });

        Schema::create('configuracion_empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_negocio')->default('San Benjamin');
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('moneda', 10)->default('Bs');
            $table->text('texto_pie_recibos')->nullable();
            $table->string('tamano_papel_recibo')->default('letter');
            $table->timestamps();
        });

        Schema::create('importaciones_excel', function (Blueprint $table) {
            $table->id();
            $table->string('archivo_original');
            $table->json('resumen');
            $table->json('errores')->nullable();
            $table->foreignId('usuario_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones_excel');
        Schema::dropIfExists('configuracion_empresa');
        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('detalle_compras');
        Schema::dropIfExists('compras');
        Schema::dropIfExists('detalle_ventas');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('proveedores');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('categorias');
    }
};
