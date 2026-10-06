<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReporteGuardado extends Model
{
    protected $table = 'reportes_guardados';

    protected $fillable = [
        'titulo',
        'tipo',
        'ruta',
        'observacion',
        'activo',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}
