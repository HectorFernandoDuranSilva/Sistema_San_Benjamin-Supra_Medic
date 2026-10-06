<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index()
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        return view('usuarios.index', [
            'usuarios' => User::orderBy('name')->paginate(20),
            'permissions' => User::PERMISSIONS,
            'permissionGroups' => User::PERMISSION_GROUPS,
            'permissionProfiles' => User::PERMISSION_PROFILES,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $this->validateUser($request);
        $data['email'] = $this->systemEmail($data['username']);
        $data['permissions'] = $this->normalizedPermissions($request);

        $usuario = User::create($data);
        app(AuditLogService::class)->record(
            $request,
            'creo',
            'usuarios',
            $usuario,
            null,
            $usuario->only(['name', 'username', 'email', 'role', 'activo', 'permissions']),
            'Usuario creado'
        );

        return back()->with('ok', 'Usuario creado.');
    }

    public function update(Request $request, User $usuario)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $this->validateUser($request, $usuario);
        $data['email'] = $this->systemEmail($data['username']);
        $data['permissions'] = $this->normalizedPermissions($request);

        if (! $request->filled('password')) {
            unset($data['password']);
        }

        if ($usuario->is($request->user())) {
            $data['role'] = 'administrador';
            $data['activo'] = true;
            $data['permissions']['usuarios'] = true;
        }

        $this->ensureActiveAdminRemains($usuario, $data);

        $before = $usuario->only(['name', 'username', 'email', 'role', 'activo', 'permissions']);
        $usuario->update($data);
        app(AuditLogService::class)->record(
            $request,
            'actualizo',
            'usuarios',
            $usuario,
            $before,
            $usuario->only(array_keys($before)),
            'Usuario actualizado'
        );

        return back()->with('ok', 'Usuario actualizado.');
    }

    public function destroy(Request $request, User $usuario)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $request->validate(['totp_code' => ['required', 'string', 'max:32']]);
        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'usuarios', $usuario, 'Intento de desactivar usuario')) {
            return back()->withErrors(['totp_code' => $error]);
        }

        if ($usuario->is($request->user())) {
            return back()->withErrors(['usuario' => 'No puede desactivar su propio usuario.']);
        }

        $this->ensureActiveAdminRemains($usuario, ['role' => $usuario->role, 'activo' => false]);

        $before = $usuario->only(['name', 'username', 'role', 'activo']);
        $usuario->update(['activo' => false]);
        app(AuditLogService::class)->record(
            $request,
            'desactivo',
            'usuarios',
            $usuario,
            $before,
            $usuario->only(array_keys($before)),
            'Usuario desactivado'
        );

        return back()->with('ok', 'Usuario desactivado.');
    }

    private function validateUser(Request $request, ?User $usuario = null): array
    {
        $passwordRules = $usuario ? ['nullable', 'string', 'min:6'] : ['required', 'string', 'min:6'];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:150', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($usuario)],
            'role' => ['required', 'in:administrador,operador'],
            'activo' => ['nullable', 'boolean'],
            'password' => $passwordRules,
        ]);
    }

    private function ensureActiveAdminRemains(User $usuario, array $data): void
    {
        if (! $usuario->isAdmin() || ! $usuario->activo) {
            return;
        }

        $willRemainAdmin = ($data['role'] ?? $usuario->role) === 'administrador';
        $willRemainActive = (bool) ($data['activo'] ?? $usuario->activo);

        if ($willRemainAdmin && $willRemainActive) {
            return;
        }

        $otherActiveAdmins = User::query()
            ->whereKeyNot($usuario->getKey())
            ->where('role', 'administrador')
            ->where('activo', true)
            ->exists();

        if (! $otherActiveAdmins) {
            throw ValidationException::withMessages([
                'usuario' => 'Debe quedar al menos un administrador activo.',
            ]);
        }
    }

    private function systemEmail(string $username): string
    {
        $slug = Str::of($username)->lower()->replaceMatches('/[^a-z0-9._-]+/', '.')->trim('.');

        return ($slug->isEmpty() ? 'usuario' : (string) $slug) . '@sistema.local';
    }

    private function normalizedPermissions(Request $request): array
    {
        $selected = $request->input('permissions', []);

        return collect(User::PERMISSIONS)
            ->keys()
            ->mapWithKeys(fn ($permission) => [$permission => in_array($permission, $selected, true)])
            ->all();
    }
}
