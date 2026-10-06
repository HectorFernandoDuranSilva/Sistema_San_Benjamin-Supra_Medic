<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->string('tipo_documento')->default('factura')->after('nombre_comercial');
        });

        Schema::table('detalle_compras', function (Blueprint $table) {
            $table->string('codigo_lote')->nullable()->after('porcentaje_utilidad');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_compras', function (Blueprint $table) {
            $table->dropColumn('codigo_lote');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn('tipo_documento');
        });
    }
};
