<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Historical stock is deliberately not valued from today's exchange rate.
            $table->decimal('costo_promedio', 20, 6)->nullable();
            $table->decimal('valor_inventario', 20, 6)->nullable();
            $table->decimal('ultimo_costo_compra', 20, 6)->nullable();
        });
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->decimal('costo_valoracion', 20, 6)->nullable();
            $table->decimal('valor_anterior', 20, 6)->nullable();
            $table->decimal('valor_nuevo', 20, 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_inventario', fn (Blueprint $table) => $table->dropColumn(['costo_valoracion', 'valor_anterior', 'valor_nuevo']));
        Schema::table('productos', fn (Blueprint $table) => $table->dropColumn(['costo_promedio', 'valor_inventario', 'ultimo_costo_compra']));
    }
};
