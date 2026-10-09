<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->boolean('permitir_salida_sin_stock')->default(false)->after('observacion');
            $table->text('motivo_salida_sin_stock')->nullable()->after('permitir_salida_sin_stock');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['permitir_salida_sin_stock', 'motivo_salida_sin_stock']);
        });
    }
};
