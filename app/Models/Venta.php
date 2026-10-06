<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $fillable = [
        'numero_venta',
        'nombre_comercial',
        'tipo_documento',
        'cliente_id',
        'fecha',
        'factura',
        'nit_cliente',
        'lista_precios',
        'subtotal',
        'descuento_porcentaje',
        'descuento',
        'impuesto_total',
        'costo_envio_total',
        'otros_costos_total',
        'total',
        'estado',
        'observacion',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'subtotal' => 'decimal:2',
            'descuento_porcentaje' => 'decimal:2',
            'descuento' => 'decimal:2',
            'impuesto_total' => 'decimal:2',
            'costo_envio_total' => 'decimal:2',
            'otros_costos_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cobros()
    {
        return $this->hasMany(Cobro::class);
    }

    public function cuotas()
    {
        return $this->hasMany(VentaCuota::class)->orderBy('numero');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
