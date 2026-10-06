<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ConfiguracionEmpresa;
use App\Models\TipoCambio;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@sistema.local')],
            [
                'name' => env('ADMIN_NAME', 'Administrador'),
                'username' => env('ADMIN_USERNAME', 'admin123'),
                'role' => 'administrador',
                'activo' => true,
                'password' => env('ADMIN_PASSWORD', 'admin123'),
            ]
        );

        ConfiguracionEmpresa::firstOrCreate([], [
            'nombre_negocio' => 'San Benjamin',
            'moneda' => 'Bs',
            'texto_pie_recibos' => 'Gracias por su compra',
            'tamano_papel_recibo' => 'letter',
        ]);

        TipoCambio::firstOrCreate(
            ['fecha' => now()->toDateString()],
            [
                'valor' => env('TIPO_CAMBIO_FALLBACK', 6.96),
                'fuente' => 'manual',
                'url_fuente' => null,
                'estado' => 'vigente',
                'observacion' => 'Valor inicial de respaldo.',
                'consultado_en' => now(),
            ]
        );
    }
}
