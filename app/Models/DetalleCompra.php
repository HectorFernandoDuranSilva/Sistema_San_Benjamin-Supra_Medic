<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleCompra extends Model
{
    protected $table = 'detalle_compras';

    protected $fillable = [
        'compra_id',
        'producto_id',
        'codigo_producto',
        'nombre_producto',
        'cantidad',
        'costo_unitario',
        'porcentaje_utilidad',
        'codigo_lote',
        'fecha_vencimiento',
        'subtotal',
        'impuesto',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'porcentaje_utilidad' => 'decimal:2',
            'fecha_vencimiento' => 'date',
            'subtotal' => 'decimal:2',
            'impuesto' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }

    public function lote()
    {
        return $this->hasOne(InventarioLote::class);
    }
}
