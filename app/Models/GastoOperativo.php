<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GastoOperativo extends Model
{
    protected $table = 'gastos_operativos';

    protected $fillable = [
        'nombre_comercial', 'tipo', 'modalidad', 'documento_tipo',
        'documento_numero', 'fecha', 'importe', 'descripcion',
        'observacion', 'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'importe' => 'decimal:2',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
