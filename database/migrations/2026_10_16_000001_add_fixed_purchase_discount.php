<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_compras', function (Blueprint $table): void {
            $table->decimal('descuento_fijo', 14, 2)->default(0)->after('descuento_porcentaje');
        });
        Schema::table('ventas', function (Blueprint $table): void {
            $table->boolean('tarjeta_magnetica')->default(false)->after('observacion');
            $table->string('tipo_tarjeta')->nullable()->after('tarjeta_magnetica');
            $table->string('destinatario_tarjeta')->nullable()->after('tipo_tarjeta');
        });
        Schema::table('detalle_ventas', function (Blueprint $table): void {
            $table->string('codigo_lote_manual')->nullable()->after('faltante_stock');
            $table->date('fecha_vencimiento_manual')->nullable()->after('codigo_lote_manual');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_compras', function (Blueprint $table): void {
            $table->dropColumn('descuento_fijo');
        });
        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropColumn(['tarjeta_magnetica', 'tipo_tarjeta', 'destinatario_tarjeta']);
        });
        Schema::table('detalle_ventas', function (Blueprint $table): void {
            $table->dropColumn(['codigo_lote_manual', 'fecha_vencimiento_manual']);
        });
    }
};
