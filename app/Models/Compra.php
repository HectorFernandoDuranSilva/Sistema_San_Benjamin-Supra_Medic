<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compra extends Model
{
    protected $table = 'compras';

    protected $fillable = [
        'numero_compra',
        'nombre_comercial',
        'tipo_documento',
        'proveedor_id',
        'fecha',
        'factura',
        'nit_proveedor',
        'subtotal',
        'descuento_total',
        'impuesto_total',
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
            'descuento_total' => 'decimal:2',
            'impuesto_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCompra::class);
    }

    public function gastos()
    {
        return $this->hasMany(CompraGasto::class)->orderBy('fecha')->orderBy('id');
    }

    public function pagos()
    {
        return $this->hasMany(PagoCompra::class);
    }

    public function cuotas()
    {
        return $this->hasMany(CompraCuota::class)->orderBy('numero');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function auditorias()
    {
        return $this->morphMany(AuditEvent::class, 'auditable')
            ->where('accion', 'anulo')
            ->latest();
    }
}
