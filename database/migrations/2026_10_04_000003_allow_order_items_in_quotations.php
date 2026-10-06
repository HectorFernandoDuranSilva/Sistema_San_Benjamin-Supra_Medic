<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_cotizaciones', function (Blueprint $table): void {
            $table->unsignedBigInteger('producto_id')->nullable()->change();
            $table->string('medida', 100)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('detalle_cotizaciones')->whereNull('producto_id')->exists()) {
            throw new RuntimeException('Vincule los articulos por pedido antes de revertir esta migracion.');
        }
        Schema::table('detalle_cotizaciones', function (Blueprint $table): void {
            $table->unsignedBigInteger('producto_id')->nullable(false)->change();
            $table->dropColumn('medida');
        });
    }
};
