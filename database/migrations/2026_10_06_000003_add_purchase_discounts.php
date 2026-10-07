<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->decimal('descuento_total', 14, 2)->default(0)->after('subtotal');
        });

        Schema::table('detalle_compras', function (Blueprint $table) {
            $table->decimal('costo_neto_unitario', 14, 4)->nullable()->after('costo_unitario');
            $table->decimal('descuento_porcentaje', 5, 2)->default(0)->after('porcentaje_utilidad');
            $table->decimal('descuento', 14, 2)->default(0)->after('subtotal');
        });

        DB::table('detalle_compras')->update([
            'costo_neto_unitario' => DB::raw('costo_unitario'),
        ]);
    }

    public function down(): void
    {
        Schema::table('detalle_compras', function (Blueprint $table) {
            $table->dropColumn(['costo_neto_unitario', 'descuento_porcentaje', 'descuento']);
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn('descuento_total');
        });
    }
};
