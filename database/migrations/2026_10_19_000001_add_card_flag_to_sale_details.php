<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table): void {
            $table->boolean('tarjeta_magnetica')->default(false)->after('nombre_producto');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table): void {
            $table->dropColumn('tarjeta_magnetica');
        });
    }
};
