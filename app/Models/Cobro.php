<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cobro extends Model
{
    protected $table = 'cobros';
    protected $fillable = ['venta_id', 'cuota_id', 'usuario_id', 'token', 'fecha', 'importe', 'medio', 'referencia', 'estado', 'motivo_anulacion'];
    protected function casts(): array { return ['fecha' => 'date', 'importe' => 'decimal:2']; }
    public function venta() { return $this->belongsTo(Venta::class); }
    public function cuota() { return $this->belongsTo(VentaCuota::class, 'cuota_id'); }
}
