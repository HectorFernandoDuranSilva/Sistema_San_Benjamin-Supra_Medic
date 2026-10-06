<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleCotizacion extends Model
{
    protected $table = 'detalle_cotizaciones';

    protected $fillable = [
        'cotizacion_id',
        'producto_id',
        'codigo_producto',
        'nombre_producto',
        'medida',
        'marca',
        'procedencia',
        'fecha_vencimiento',
        'inventario_lote_id',
        'cantidad',
        'costo_referencial',
        'porcentaje_utilidad',
        'precio_unitario',
        'subtotal',
        'descuento',
        'impuesto',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'cantidad' => 'decimal:2',
            'costo_referencial' => 'decimal:2',
            'porcentaje_utilidad' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'impuesto' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
