<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('detalle_ventas')
            ->where('total', 0)
            ->where('subtotal', '>', 0)
            ->update([
                'total' => DB::raw('subtotal - descuento + impuesto + costo_envio + otros_costos'),
            ]);
    }

    public function down(): void
    {
        //
    }
};
