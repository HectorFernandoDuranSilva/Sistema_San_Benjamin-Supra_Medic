<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_compras', function (Blueprint $table) {
            $table->date('fecha_vencimiento')->nullable()->after('porcentaje_utilidad');
        });

        Schema::table('configuracion_empresa', function (Blueprint $table) {
            $table->unsignedSmallInteger('dias_alerta_vencimiento')->default(30)->after('tamano_papel_recibo');
        });

        Schema::create('inventario_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();
            $table->foreignId('detalle_compra_id')->nullable()->constrained('detalle_compras')->nullOnDelete();
            $table->string('codigo_lote')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('cantidad_inicial', 14, 2);
            $table->decimal('cantidad_disponible', 14, 2);
            $table->decimal('costo_unitario', 14, 2)->default(0);
            $table->string('estado')->default('activo');
            $table->text('observacion')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['producto_id', 'estado', 'fecha_vencimiento']);
        });

        Schema::create('detalle_venta_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detalle_venta_id')->constrained('detalle_ventas')->cascadeOnDelete();
            $table->foreignId('inventario_lote_id')->constrained('inventario_lotes');
            $table->decimal('cantidad', 14, 2);
            $table->date('fecha_vencimiento')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_venta_lotes');
        Schema::dropIfExists('inventario_lotes');

        Schema::table('configuracion_empresa', function (Blueprint $table) {
            $table->dropColumn('dias_alerta_vencimiento');
        });

        Schema::table('detalle_compras', function (Blueprint $table) {
            $table->dropColumn('fecha_vencimiento');
        });
    }
};
