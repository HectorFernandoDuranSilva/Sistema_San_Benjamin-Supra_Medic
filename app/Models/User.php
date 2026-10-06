<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'role', 'activo', 'permissions', 'dashboard_preferences', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_failed_attempts', 'two_factor_locked_until'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const PERMISSIONS = [
        'dashboard' => 'Dashboard',
        'ventas' => 'Ventas',
        'compras' => 'Compras',
        'cotizaciones' => 'Cotizaciones',
        'inventario' => 'Inventario',
        'productos' => 'Productos y categorias',
        'clientes' => 'Clientes',
        'proveedores' => 'Proveedores',
        'reportes' => 'Reportes',
        'configuracion' => 'Configuracion',
        'importar' => 'Importacion Excel',
        'usuarios' => 'Usuarios y permisos',
        'seguridad' => 'Seguridad',
        'notas_facturas' => 'Notas y facturas',
        'balance_mensual' => 'Balance mensual',
        'kardex' => 'Kardex',
        'historial_clientes' => 'Historial por cliente',
    ];

    public const PERMISSION_PROFILES = [
        'operador_ventas' => [
            'label' => 'Operador de ventas',
            'permissions' => ['dashboard', 'ventas', 'cotizaciones', 'inventario', 'clientes', 'reportes', 'notas_facturas', 'balance_mensual', 'kardex', 'historial_clientes'],
        ],
        'operador_compras' => [
            'label' => 'Operador de compras',
            'permissions' => ['dashboard', 'compras', 'inventario', 'productos', 'proveedores', 'reportes', 'notas_facturas', 'balance_mensual', 'kardex', 'historial_clientes'],
        ],
        'inventario' => [
            'label' => 'Inventario y lotes',
            'permissions' => ['dashboard', 'inventario', 'productos', 'reportes', 'kardex'],
        ],
        'reportes' => [
            'label' => 'Reportes y kardex',
            'permissions' => ['dashboard', 'inventario', 'reportes', 'notas_facturas', 'balance_mensual', 'kardex', 'historial_clientes'],
        ],
        'administrador' => [
            'label' => 'Administrador completo',
            'permissions' => ['dashboard', 'ventas', 'compras', 'cotizaciones', 'inventario', 'productos', 'clientes', 'proveedores', 'reportes', 'configuracion', 'importar', 'usuarios', 'seguridad', 'notas_facturas', 'balance_mensual', 'kardex', 'historial_clientes'],
        ],
    ];

    public const PERMISSION_GROUPS = [
        'dashboard' => ['label' => 'Dashboard', 'permissions' => ['dashboard']],
        'operacion' => ['label' => 'Operación', 'permissions' => ['ventas', 'compras', 'cotizaciones']],
        'inventario' => ['label' => 'Inventario', 'permissions' => ['inventario', 'productos']],
        'reportes' => ['label' => 'Reportes', 'permissions' => ['reportes', 'notas_facturas', 'balance_mensual', 'kardex', 'historial_clientes']],
        'administracion' => ['label' => 'Administración', 'permissions' => ['clientes', 'proveedores', 'configuracion', 'usuarios', 'seguridad']],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'activo' => 'boolean',
            'permissions' => 'array',
            'dashboard_preferences' => 'array',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_failed_attempts' => 'integer',
            'two_factor_locked_until' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'administrador';
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->permissions === null) {
            return true;
        }

        if (! array_key_exists($permission, $this->permissions)) {
            $legacyReportPermissions = ['notas_facturas', 'balance_mensual', 'kardex', 'historial_clientes'];

            if ($permission === 'seguridad') {
                return $this->isAdmin();
            }

            return in_array($permission, $legacyReportPermissions, true)
                ? (bool) data_get($this->permissions, 'reportes', false)
                : false;
        }

        return (bool) data_get($this->permissions, $permission, false);
    }
}
