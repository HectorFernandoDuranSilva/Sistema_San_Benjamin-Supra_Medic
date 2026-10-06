<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleVentaLote extends Model
{
    protected $table = 'detalle_venta_lotes';

    protected $fillable = [
        'detalle_venta_id',
        'inventario_lote_id',
        'cantidad',
        'fecha_vencimiento',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'fecha_vencimiento' => 'date',
        ];
    }

    public function detalleVenta()
    {
        return $this->belongsTo(DetalleVenta::class);
    }

    public function lote()
    {
        return $this->belongsTo(InventarioLote::class, 'inventario_lote_id');
    }
}
