<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    protected $table = 'auditoria_eventos';

    protected $fillable = [
        'user_id',
        'accion',
        'modulo',
        'auditable_type',
        'auditable_id',
        'descripcion',
        'valores_anteriores',
        'valores_nuevos',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'valores_anteriores' => 'array',
            'valores_nuevos' => 'array',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function auditable()
    {
        return $this->morphTo();
    }
}
