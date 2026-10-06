<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuracion_empresa', function (Blueprint $table) {
            $table->unsignedSmallInteger('dias_alerta_cuotas')->default(10)->after('dias_alerta_vencimiento');
            $table->json('dashboard_opciones')->nullable()->after('dias_alerta_cuotas');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_empresa', function (Blueprint $table) {
            $table->dropColumn(['dias_alerta_cuotas', 'dashboard_opciones']);
        });
    }
};
