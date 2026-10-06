<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_cotizaciones', function (Blueprint $table): void {
            $table->string('marca')->nullable();
            $table->string('procedencia')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->foreignId('inventario_lote_id')->nullable()->constrained('inventario_lotes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('detalle_cotizaciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inventario_lote_id');
            $table->dropColumn(['marca', 'procedencia', 'fecha_vencimiento']);
        });
    }
};
