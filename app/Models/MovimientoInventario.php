<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'producto_id',
        'tipo_movimiento',
        'cantidad',
        'stock_anterior',
        'stock_nuevo',
        'referencia_tipo',
        'referencia_id',
        'observacion',
        'usuario_id',
        'fecha_movimiento',
        'costo_valoracion',
        'valor_anterior',
        'valor_nuevo',
    ];

    protected function casts(): array
    {
        return [
            'costo_valoracion' => 'decimal:6',
            'valor_anterior' => 'decimal:6',
            'valor_nuevo' => 'decimal:6',
            'cantidad' => 'decimal:2',
            'stock_anterior' => 'decimal:2',
            'stock_nuevo' => 'decimal:2',
            'fecha_movimiento' => 'datetime',
        ];
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
