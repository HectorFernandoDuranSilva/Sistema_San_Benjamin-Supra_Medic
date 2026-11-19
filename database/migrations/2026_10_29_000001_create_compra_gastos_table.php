<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->string('documento_tipo', 30)->default('sin_documento');
            $table->string('documento_numero')->nullable();
            $table->date('fecha');
            $table->string('modalidad', 30)->default('otro');
            $table->string('descripcion')->nullable();
            $table->decimal('importe', 14, 2);
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->index(['compra_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_gastos');
    }
};
