<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioLote extends Model
{
    protected $table = 'inventario_lotes';

    protected $fillable = [
        'producto_id',
        'compra_id',
        'detalle_compra_id',
        'codigo_lote',
        'fecha_vencimiento',
        'cantidad_inicial',
        'cantidad_disponible',
        'cantidad_consumida_ajustada',
        'costo_unitario',
        'estado',
        'observacion',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'cantidad_inicial' => 'decimal:2',
            'cantidad_disponible' => 'decimal:2',
            'cantidad_consumida_ajustada' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
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

    public function detalleCompra()
    {
        return $this->belongsTo(DetalleCompra::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }

    public function consumos()
    {
        return $this->hasMany(DetalleVentaLote::class);
    }
}
