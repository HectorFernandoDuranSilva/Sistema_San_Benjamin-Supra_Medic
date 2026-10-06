<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cobros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->uuid('token')->unique();
            $table->date('fecha');
            $table->decimal('importe', 16, 2);
            $table->string('medio');
            $table->string('referencia')->nullable();
            $table->string('estado')->default('registrado');
            $table->string('motivo_anulacion')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('cobros'); }
};
