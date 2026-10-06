<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = ['nombre', 'razon_social', 'documento', 'telefono', 'direccion', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function scopeCoincideConBusqueda($query, string $buscar)
    {
        $termino = '%' . Str::lower(Str::ascii(trim($buscar))) . '%';

        return $query->where(function ($query) use ($termino) {
            $query->whereRaw($this->columnaBusquedaNormalizada('nombre') . ' like ?', [$termino])
                ->orWhereRaw($this->columnaBusquedaNormalizada('razon_social') . ' like ?', [$termino])
                ->orWhere('documento', 'like', $termino);
        });
    }

    public function getNombreMostrableAttribute(): string
    {
        $nombre = trim((string) $this->nombre);
        $razonSocial = trim((string) $this->razon_social);

        if ($razonSocial === '') {
            return $nombre;
        }

        $nombreNormalizado = Str::lower(Str::ascii($nombre));
        $razonNormalizada = Str::lower(Str::ascii($razonSocial));

        if ($razonNormalizada === 'institucion') {
            return $nombre . ' (Institución)';
        }

        if (
            in_array($razonNormalizada, ['hospital', 'municipio', 'particular'], true)
            || $nombreNormalizado === $razonNormalizada
            || str_contains($nombreNormalizado, $razonNormalizada)
        ) {
            return $nombre;
        }

        return $nombre . ' - ' . $razonSocial;
    }

    private function columnaBusquedaNormalizada(string $columna): string
    {
        $expresion = "lower({$columna})";

        foreach ([
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ] as $original => $reemplazo) {
            $expresion = "replace({$expresion}, '{$original}', '{$reemplazo}')";
        }

        return $expresion;
    }
}
