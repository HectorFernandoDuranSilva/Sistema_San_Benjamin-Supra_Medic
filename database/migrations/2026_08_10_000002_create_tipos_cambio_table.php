<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_cambio', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->decimal('valor', 14, 4);
            $table->string('moneda_origen', 10)->default('USD');
            $table->string('moneda_destino', 10)->default('BOB');
            $table->string('fuente')->default('BCB');
            $table->string('url_fuente')->nullable();
            $table->string('estado')->default('vigente');
            $table->text('observacion')->nullable();
            $table->timestamp('consultado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_cambio');
    }
};
