<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraGasto extends Model
{
    protected $table = 'compra_gastos';

    protected $fillable = [
        'compra_id', 'tipo', 'documento_tipo', 'documento_numero', 'fecha',
        'modalidad', 'descripcion', 'importe', 'observacion',
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
