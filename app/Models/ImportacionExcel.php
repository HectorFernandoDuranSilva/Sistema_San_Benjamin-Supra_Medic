<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportacionExcel extends Model
{
    protected $table = 'importaciones_excel';

    protected $fillable = ['archivo_original', 'resumen', 'errores', 'usuario_id'];

    protected function casts(): array
    {
        return [
            'resumen' => 'array',
            'errores' => 'array',
        ];
    }
}
