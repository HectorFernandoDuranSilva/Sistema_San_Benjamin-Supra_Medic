<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = [
        'codigo',
        'nombre',
        'marca',
        'procedencia',
        'categoria_id',
        'descripcion',
        'medida',
        'ubicacion',
        'moneda_costo',
        'precio_ingreso_usd',
        'tipo_cambio',
        'porcentaje_utilidad',
        'precio_venta',
        'costo_referencial',
        'stock_minimo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'costo_promedio' => 'decimal:6',
            'valor_inventario' => 'decimal:6',
            'ultimo_costo_compra' => 'decimal:6',
            'precio_venta' => 'decimal:2',
            'costo_referencial' => 'decimal:2',
            'precio_ingreso_usd' => 'decimal:2',
            'tipo_cambio' => 'decimal:4',
            'porcentaje_utilidad' => 'decimal:2',
            'stock_minimo' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function getPrecioSugeridoAttribute(): ?float
    {
        return $this->costo_promedio === null ? null
            : round((float) $this->costo_promedio * (1 + (float) $this->porcentaje_utilidad / 100), 2);
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    public function detalleVentas()
    {
        return $this->hasMany(DetalleVenta::class);
    }

    public function detalleCompras()
    {
        return $this->hasMany(DetalleCompra::class);
    }

    public function lotes()
    {
        return $this->hasMany(InventarioLote::class);
    }

    public function getStockActualAttribute(): float
    {
        $ultimo = $this->movimientos()->latest('fecha_movimiento')->latest('id')->first();

        return (float) ($ultimo?->stock_nuevo ?? 0);
    }
}
