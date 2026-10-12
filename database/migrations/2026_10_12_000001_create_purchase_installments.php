<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('compra_cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->date('fecha_vencimiento');
            $table->decimal('importe', 16, 2);
            $table->string('observacion')->nullable();
            $table->timestamps();
            $table->unique(['compra_id', 'numero']);
            $table->index(['fecha_vencimiento', 'compra_id']);
        });
        Schema::table('pagos_compras', function (Blueprint $table) {
            $table->foreignId('cuota_id')->nullable()->after('compra_id')->constrained('compra_cuotas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pagos_compras', function (Blueprint $table) { $table->dropConstrainedForeignId('cuota_id'); });
        Schema::dropIfExists('compra_cuotas');
    }
};
