<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table): void {
            $table->timestamp('confirmada_at')->nullable();
            $table->foreignId('confirmada_por_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('confirmada_por_id');
            $table->dropColumn('confirmada_at');
        });
    }
};
