@extends('layouts.app')

@section('content')
<div class="top">
    <h1>Usuarios y permisos</h1>
    <div class="actions"><a class="btn secondary" href="{{ route('dashboard') }}">Dashboard</a></div>
</div>

<form class="card" method="post" action="{{ route('usuarios.store') }}" style="margin-bottom:16px;">
    @csrf
    <h2>Crear usuario</h2>
    <div class="row">
        <div class="field"><label>Nombre</label><input name="name" value="{{ old('name') }}" required></div>
        <div class="field"><label>Usuario</label><input name="username" value="{{ old('username') }}" pattern="[A-Za-z0-9._-]+" maxlength="150" autocomplete="username" required></div>
        <div class="field"><label>Contraseña</label><input type="password" name="password" autocomplete="new-password" required></div>
    </div>
    <div class="row">
        <div class="field"><label>Rol</label><select name="role"><option value="operador">Operador</option><option value="administrador">Administrador</option></select></div>
        <div class="field"><label>Estado</label><select name="activo"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
        <div class="field">
            <label>Perfil de permisos</label>
            <select name="permission_profile" data-permission-profile data-profile-target="create">
                <option value="">Personalizado</option>
                @foreach($permissionProfiles as $profileKey => $profile)
                    <option value="{{ $profileKey }}">{{ $profile['label'] }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="card" style="margin:8px 0 12px;">
        <strong>Permisos por módulo</strong>
        <div class="grid cols-2" style="margin-top:10px;">
            @foreach($permissionGroups as $group)
                <section class="field"><strong>{{ $group['label'] }}</strong>
                    @foreach($group['permissions'] as $key)
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin:8px 0 0;"><input type="checkbox" name="permissions[]" value="{{ $key }}" checked data-permission-checkbox="create" style="width:auto;">{{ $permissions[$key] }}</label>
                    @endforeach
                </section>
            @endforeach
        </div>
    </div>
    <div class="actions"><button class="btn">Crear usuario</button></div>
</form>

<div class="card">
    <h2>Usuarios registrados</h2>
    <table>
        <tr><th>Usuario</th><th>Rol</th><th>Estado</th><th>Permisos</th><th>Acciones</th></tr>
        @foreach($usuarios as $usuario)
            <tr>
                <td>
                    <form id="usuario-edit-{{ $usuario->id }}" method="post" action="{{ route('usuarios.update', $usuario) }}">
                        @csrf
                        @method('put')
                    </form>
                    <div class="row" style="grid-template-columns:repeat(2,minmax(0,1fr));">
                        <input form="usuario-edit-{{ $usuario->id }}" name="name" value="{{ old('name', $usuario->name) }}" required>
                        <input form="usuario-edit-{{ $usuario->id }}" name="username" value="{{ old('username', $usuario->username) }}" pattern="[A-Za-z0-9._-]+" maxlength="150" autocomplete="username" required>
                    </div>
                    <input form="usuario-edit-{{ $usuario->id }}" type="password" name="password" placeholder="Nueva contrasena opcional" autocomplete="new-password" style="margin-top:8px;">
                </td>
                <td><select form="usuario-edit-{{ $usuario->id }}" name="role"><option value="operador" @selected($usuario->role === 'operador')>Operador</option><option value="administrador" @selected($usuario->role === 'administrador')>Administrador</option></select></td>
                <td><select form="usuario-edit-{{ $usuario->id }}" name="activo"><option value="1" @selected($usuario->activo)>Activo</option><option value="0" @selected(! $usuario->activo)>Inactivo</option></select></td>
                <td>
                    <select data-permission-profile data-profile-target="user-{{ $usuario->id }}" style="margin-bottom:10px;">
                        <option value="">Perfil rápido</option>
                        @foreach($permissionProfiles as $profileKey => $profile)
                            <option value="{{ $profileKey }}">{{ $profile['label'] }}</option>
                        @endforeach
                    </select>
                    <div class="grid cols-2">
                        @foreach($permissionGroups as $group)
                            <section class="field"><strong style="font-size:12px;">{{ $group['label'] }}</strong>
                                @foreach($group['permissions'] as $key)
                                    <label style="display:flex;align-items:center;gap:8px;font-size:12px;margin:8px 0 0;"><input form="usuario-edit-{{ $usuario->id }}" type="checkbox" name="permissions[]" value="{{ $key }}" @checked($usuario->permissions === null || data_get($usuario->permissions, $key)) data-permission-checkbox="user-{{ $usuario->id }}" style="width:auto;">{{ $permissions[$key] }}</label>
                                @endforeach
                            </section>
                        @endforeach
                    </div>
                </td>
                <td>
                    <div class="actions" style="gap:8px;">
                        <button form="usuario-edit-{{ $usuario->id }}" class="btn secondary">Guardar</button>
                        @if(! $usuario->is(auth()->user()))
                            <form method="post" action="{{ route('usuarios.destroy', $usuario) }}" data-glass-confirm="Esta acción desactivara el usuario. ¿Desea continuar?">
                                @csrf
                                @method('delete')
                                <input name="totp_code" inputmode="numeric" maxlength="32" placeholder="Código Authenticator" required>
                                <button class="btn danger">Desactivar</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </table>
    {{ $usuarios->links() }}
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const permissionProfiles = @json($permissionProfiles);

    document.querySelectorAll('[data-permission-profile]').forEach(function (select) {
        select.addEventListener('change', function () {
            const profile = permissionProfiles[select.value];
            const target = select.dataset.profileTarget;

            if (! profile || ! target) {
                return;
            }

            document.querySelectorAll('[data-permission-checkbox="' + target + '"]').forEach(function (checkbox) {
                checkbox.checked = profile.permissions.includes(checkbox.value);
            });
        });
    });
});
</script>
@endsection
