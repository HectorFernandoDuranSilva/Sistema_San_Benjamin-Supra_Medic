<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCambio extends Model
{
    protected $table = 'tipos_cambio';

    protected $fillable = [
        'fecha',
        'valor',
        'moneda_origen',
        'moneda_destino',
        'fuente',
        'url_fuente',
        'estado',
        'observacion',
        'consultado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'valor' => 'decimal:4',
            'consultado_en' => 'datetime',
        ];
    }

    public static function ultimoValor(float $fallback = 6.96): float
    {
        $tipoCambio = self::where('estado', 'vigente')
            ->latest('fecha')
            ->latest('id')
            ->first();

        return $tipoCambio ? (float) $tipoCambio->valor : $fallback;
    }
}
