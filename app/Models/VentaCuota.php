<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentaCuota extends Model
{
    protected $table = 'venta_cuotas';

    protected $fillable = ['venta_id', 'numero', 'fecha_vencimiento', 'importe', 'observacion'];

    protected function casts(): array
    {
        return ['fecha_vencimiento' => 'date', 'importe' => 'decimal:2'];
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function cobros()
    {
        return $this->hasMany(Cobro::class, 'cuota_id');
    }
}
