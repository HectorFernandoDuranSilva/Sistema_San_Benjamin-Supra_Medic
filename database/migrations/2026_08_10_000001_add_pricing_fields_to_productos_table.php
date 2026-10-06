<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio_ingreso_usd', 14, 2)->default(0)->after('ubicacion');
            $table->decimal('tipo_cambio', 14, 4)->default(6.9600)->after('precio_ingreso_usd');
            $table->decimal('porcentaje_utilidad', 5, 2)->default(27.00)->after('tipo_cambio');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['precio_ingreso_usd', 'tipo_cambio', 'porcentaje_utilidad']);
        });
    }
};
