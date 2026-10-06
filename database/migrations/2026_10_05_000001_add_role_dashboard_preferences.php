<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuracion_empresa', function (Blueprint $table) {
            $table->json('dashboard_opciones_por_rol')->nullable()->after('dashboard_opciones');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_empresa', function (Blueprint $table) {
            $table->dropColumn('dashboard_opciones_por_rol');
        });
    }
};
