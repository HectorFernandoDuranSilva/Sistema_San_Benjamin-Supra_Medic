<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraCuota extends Model
{
    protected $table = 'compra_cuotas';
    protected $fillable = ['compra_id', 'numero', 'fecha_vencimiento', 'importe', 'observacion'];
    protected function casts(): array { return ['fecha_vencimiento' => 'date', 'importe' => 'decimal:2']; }
    public function compra() { return $this->belongsTo(Compra::class); }
    public function pagos() { return $this->hasMany(PagoCompra::class, 'cuota_id'); }
}
