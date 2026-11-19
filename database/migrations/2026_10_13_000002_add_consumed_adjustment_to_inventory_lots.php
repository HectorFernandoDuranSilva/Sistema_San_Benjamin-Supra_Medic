<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_lotes', function (Blueprint $table): void {
            $table->decimal('cantidad_consumida_ajustada', 14, 2)->nullable()->after('cantidad_disponible');
        });
    }

    public function down(): void
    {
        Schema::table('inventario_lotes', function (Blueprint $table): void {
            $table->dropColumn('cantidad_consumida_ajustada');
        });
    }
};
