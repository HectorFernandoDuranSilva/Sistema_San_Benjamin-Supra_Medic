<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos_operativos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_comercial', 100)->index();
            $table->string('tipo', 40);
            $table->string('modalidad', 40);
            $table->string('documento_tipo', 40);
            $table->string('documento_numero', 255)->nullable();
            $table->date('fecha')->index();
            $table->decimal('importe', 14, 2);
            $table->string('descripcion', 255)->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos_operativos');
    }
};
