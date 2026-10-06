<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';

    protected $fillable = [
        'numero_cotizacion',
        'nombre_comercial',
        'cliente_id',
        'fecha',
        'valida_hasta',
        'nit_cliente',
        'subtotal',
        'descuento_porcentaje',
        'descuento',
        'impuesto_total',
        'total',
        'estado',
        'observacion',
        'footer_direccion',
        'footer_validez',
        'footer_entrega',
        'footer_condiciones_pago',
        'footer_deposito_cuenta',
        'venta_id',
        'confirmada_at',
        'confirmada_por_id',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'valida_hasta' => 'date',
            'subtotal' => 'decimal:2',
            'descuento_porcentaje' => 'decimal:2',
            'descuento' => 'decimal:2',
            'impuesto_total' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmada_at' => 'datetime',
        ];
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCotizacion::class);
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function confirmadaPor()
    {
        return $this->belongsTo(User::class, 'confirmada_por_id');
    }
}
