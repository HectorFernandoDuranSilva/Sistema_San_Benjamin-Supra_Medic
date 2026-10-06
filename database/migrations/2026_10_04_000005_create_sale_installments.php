<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('venta_cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->date('fecha_vencimiento');
            $table->decimal('importe', 16, 2);
            $table->string('observacion')->nullable();
            $table->timestamps();

            $table->unique(['venta_id', 'numero']);
            $table->index(['fecha_vencimiento', 'venta_id']);
        });

        Schema::table('cobros', function (Blueprint $table) {
            $table->foreignId('cuota_id')->nullable()->after('venta_id')->constrained('venta_cuotas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cobros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuota_id');
        });
        Schema::dropIfExists('venta_cuotas');
    }
};
