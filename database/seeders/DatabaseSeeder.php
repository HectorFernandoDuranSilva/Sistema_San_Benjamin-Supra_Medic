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
        $email = env('ADMIN_EMAIL');
        $username = env('ADMIN_USERNAME');
        $password = env('ADMIN_PASSWORD');

        if ($email && $username && $password) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => env('ADMIN_NAME', 'Administrador'),
                    'username' => $username,
                    'role' => 'administrador',
                    'activo' => true,
                    'password' => $password,
                ]
            );
        }

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
