<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoCompra extends Model
{
    protected $table = 'pagos_compras';

    protected $fillable = [
        'compra_id',
        'usuario_id',
        'token',
        'fecha',
        'importe',
        'medio',
        'referencia',
        'estado',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'importe' => 'decimal:2'];
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }
}
