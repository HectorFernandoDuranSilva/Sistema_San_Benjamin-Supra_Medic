<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionEmpresa extends Model
{
    protected $table = 'configuracion_empresa';

    protected $fillable = [
        'nombre_negocio',
        'direccion',
        'telefono',
        'logo_path',
        'moneda',
        'texto_pie_recibos',
        'tamano_papel_recibo',
        'dias_alerta_vencimiento',
        'dias_alerta_cuotas',
        'dashboard_opciones',
        'dashboard_opciones_por_rol',
    ];

    protected function casts(): array
    {
        return [
            'dias_alerta_vencimiento' => 'integer',
            'dias_alerta_cuotas' => 'integer',
            'dashboard_opciones' => 'array',
            'dashboard_opciones_por_rol' => 'array',
        ];
    }
}
