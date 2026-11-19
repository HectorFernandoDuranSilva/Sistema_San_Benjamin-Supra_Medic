<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleVenta extends Model
{
    protected $table = 'detalle_ventas';

    protected $fillable = [
        'venta_id',
        'producto_id',
        'codigo_producto',
        'nombre_producto',
        'tarjeta_magnetica',
        'cantidad',
        'faltante_stock',
        'codigo_lote_manual',
        'fecha_vencimiento_manual',
        'precio_unitario',
        'subtotal',
        'descuento',
        'impuesto',
        'costo_envio',
        'otros_costos',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'tarjeta_magnetica' => 'boolean',
            'faltante_stock' => 'decimal:2',
            'fecha_vencimiento_manual' => 'date',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'impuesto' => 'decimal:2',
            'costo_envio' => 'decimal:2',
            'otros_costos' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function lotes()
    {
        return $this->hasMany(DetalleVentaLote::class);
    }
}
