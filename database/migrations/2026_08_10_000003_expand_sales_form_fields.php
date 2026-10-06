<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('nombre_comercial')->nullable()->after('numero_venta');
            $table->string('factura')->nullable()->after('fecha');
            $table->string('nit_cliente')->nullable()->after('factura');
            $table->string('lista_precios')->default('Precio de Venta')->after('nit_cliente');
            $table->decimal('descuento_porcentaje', 5, 2)->default(0)->after('subtotal');
            $table->decimal('impuesto_total', 14, 2)->default(0)->after('descuento');
            $table->decimal('costo_envio_total', 14, 2)->default(0)->after('impuesto_total');
            $table->decimal('otros_costos_total', 14, 2)->default(0)->after('costo_envio_total');
        });

        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->decimal('descuento', 14, 2)->default(0)->after('subtotal');
            $table->decimal('impuesto', 14, 2)->default(0)->after('descuento');
            $table->decimal('costo_envio', 14, 2)->default(0)->after('impuesto');
            $table->decimal('otros_costos', 14, 2)->default(0)->after('costo_envio');
            $table->decimal('total', 14, 2)->default(0)->after('otros_costos');
        });

        DB::table('detalle_ventas')
            ->where('total', 0)
            ->where('subtotal', '>', 0)
            ->update(['total' => DB::raw('subtotal')]);
    }

    public function down(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->dropColumn(['descuento', 'impuesto', 'costo_envio', 'otros_costos', 'total']);
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'nombre_comercial',
                'factura',
                'nit_cliente',
                'lista_precios',
                'descuento_porcentaje',
                'impuesto_total',
                'costo_envio_total',
                'otros_costos_total',
            ]);
        });
    }
};
