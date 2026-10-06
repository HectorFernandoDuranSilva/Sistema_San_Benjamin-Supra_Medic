<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('footer_direccion')->nullable()->after('observacion');
            $table->string('footer_validez')->nullable()->after('footer_direccion');
            $table->text('footer_entrega')->nullable()->after('footer_validez');
            $table->string('footer_condiciones_pago')->nullable()->after('footer_entrega');
            $table->text('footer_deposito_cuenta')->nullable()->after('footer_condiciones_pago');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn([
                'footer_direccion',
                'footer_validez',
                'footer_entrega',
                'footer_condiciones_pago',
                'footer_deposito_cuenta',
            ]);
        });
    }
};
