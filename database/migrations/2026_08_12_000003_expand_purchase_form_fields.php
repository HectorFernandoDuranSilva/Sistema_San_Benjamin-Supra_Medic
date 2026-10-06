<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->string('factura')->nullable()->after('fecha');
            $table->string('nit_proveedor')->nullable()->after('factura');
            $table->decimal('impuesto_total', 14, 2)->default(0)->after('subtotal');
        });

        Schema::table('detalle_compras', function (Blueprint $table) {
            $table->decimal('impuesto', 14, 2)->default(0)->after('subtotal');
            $table->decimal('total', 14, 2)->default(0)->after('impuesto');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_compras', function (Blueprint $table) {
            $table->dropColumn(['impuesto', 'total']);
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn(['factura', 'nit_proveedor', 'impuesto_total']);
        });
    }
};
