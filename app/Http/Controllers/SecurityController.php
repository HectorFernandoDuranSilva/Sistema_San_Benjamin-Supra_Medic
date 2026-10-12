<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function show(Request $request): View
    {
        $user = $this->administrator($request);
        $secret = $request->session()->get('two_factor_enrollment_secret');

        return view('seguridad.authenticator', [
            'user' => $user,
            'setupSecret' => $secret,
            'qrCode' => $secret ? $this->twoFactor->qrCodeDataUri($user, $secret) : null,
            'recoveryCodes' => $request->session()->get('two_factor_recovery_codes', []),
        ]);
    }

    public function begin(Request $request): RedirectResponse
    {
        $this->administrator($request);
        $request->session()->put('two_factor_enrollment_secret', $this->twoFactor->generateSecret());

        return redirect()->route('seguridad.authenticator')->with('success', 'Escanee el código y confirme el primer código de seis dígitos.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $this->administrator($request);
        $data = $request->validate(['totp_code' => ['required', 'string', 'max:32']]);
        $secret = $request->session()->get('two_factor_enrollment_secret');
        if (! $secret) {
            return redirect()->route('seguridad.authenticator')->withErrors(['totp_code' => 'Inicie nuevamente la configuración del autenticador.']);
        }

        $codes = $this->twoFactor->confirmEnrollment($user, $secret, $data['totp_code']);
        if (! $codes) {
            return back()->withErrors(['totp_code' => 'El código no coincide. Revise la hora de su teléfono e inténtelo otra vez.']);
        }

        $request->session()->forget('two_factor_enrollment_secret');
        $request->session()->flash('two_factor_recovery_codes', $codes);
        $this->auditLog->record($request, 'configuro_autenticador', 'seguridad', $user, null, null, 'Configuró Google Authenticator y códigos de recuperación.');

        return redirect()->route('seguridad.authenticator')->with('success', 'Google Authenticator quedó activado. Guarde los códigos de recuperación ahora.');
    }

    public function restart(Request $request): RedirectResponse
    {
        $user = $this->administrator($request);
        $request->validate(['totp_code' => ['required', 'string', 'max:32']]);
        $error = $this->twoFactor->authorize($request, 'seguridad', $user, 'Intento de reinicio del autenticador');
        if ($error) {
            return back()->withErrors(['totp_code' => $error]);
        }

        $request->session()->put('two_factor_enrollment_secret', $this->twoFactor->generateSecret());
        $this->auditLog->record($request, 'reinicio_autenticador', 'seguridad', $user, null, null, 'Inició el cambio de Google Authenticator.');

        return redirect()->route('seguridad.authenticator')->with('success', 'Escanee el nuevo código y confírmelo para completar el cambio.');
    }

    public function dashboard(Request $request): View
    {
        $this->administrator($request);

        return view('seguridad.dashboard', [
            'usuarios' => User::where('activo', true)->orderBy('name')->get(),
            'quickItems' => $this->quickItems(),
            'alertItems' => $this->alertItems(),
        ]);
    }

    public function updateDashboard(Request $request, User $usuario): RedirectResponse
    {
        $this->administrator($request);
        $data = $request->validate([
            'totp_code' => ['required', 'string', 'max:32'],
            'dashboard_preferences' => ['nullable', 'array'],
            'dashboard_preferences.*' => ['boolean'],
        ]);
        if ($error = $this->twoFactor->authorize($request, 'seguridad', $usuario, 'Intento de cambiar visibilidad del dashboard')) {
            return back()->withErrors(['totp_code' => $error]);
        }

        $before = $usuario->dashboard_preferences;
        $preferences = collect(array_keys($this->quickItems() + $this->alertItems()))
            ->mapWithKeys(fn (string $key): array => [$key => (bool) data_get($data, "dashboard_preferences.{$key}", false)])
            ->all();
        $usuario->update(['dashboard_preferences' => $preferences]);
        $this->auditLog->record($request, 'actualizo_dashboard_usuario', 'seguridad', $usuario, ['dashboard_preferences' => $before], ['dashboard_preferences' => $preferences], 'Actualizó la visibilidad del dashboard para '.$usuario->username.'.');

        return back()->with('ok', 'Visibilidad del dashboard actualizada para '.$usuario->name.'.');
    }

    /** @return array<string, string> */
    private function quickItems(): array
    {
        return [
            'ventas_hoy' => 'Ventas del día',
            'total_vendido' => 'Total vendido',
            'stock_bajo' => 'Stock bajo',
            'stock_pendiente' => 'Faltantes por regularizar',
            'movimientos' => 'Movimientos de inventario',
            'cuentas_cobrar' => 'Cuentas por cobrar',
            'movimiento_mensual' => 'Movimiento mensual',
            'ultimas_operaciones' => 'Últimas operaciones',
        ];
    }

    /** @return array<string, string> */
    private function alertItems(): array
    {
        return [
            'alertas_datos' => 'Alertas de datos',
            'faltantes_stock' => 'Aviso de faltantes de stock',
            'vencimientos' => 'Vencimientos próximos',
            'cuotas' => 'Cuotas vencidas',
            'facturacion_mensual' => 'Aviso de facturación mensual',
        ];
    }

    private function administrator(Request $request): User
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        return $user;
    }
}
