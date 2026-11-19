@extends('layouts.app')

@section('content')
<div class="top">
    <div><h1>Visibilidad del dashboard</h1><p class="muted">Defina qué información puede ver cada usuario. Ocultar un elemento no elimina datos ni modifica cálculos.</p></div>
    <div class="actions"><a class="btn secondary" href="{{ route('seguridad.authenticator') }}">Google Authenticator</a><a class="btn secondary" href="{{ route('seguridad.auditoria') }}">Auditoría</a></div>
</div>

@foreach($usuarios as $usuario)
    @php($preferences = $usuario->dashboard_preferences ?? [])
    <form class="card" method="post" action="{{ route('seguridad.dashboard.update', $usuario) }}" style="margin-bottom:16px;">
        @csrf
        @method('put')
        <h2 style="font-size:18px;">{{ $usuario->name }} <span class="muted">({{ $usuario->username }} · {{ ucfirst($usuario->role) }})</span></h2>
        <div class="grid cols-2" style="margin:12px 0;">
            <section>
                <h3 style="font-size:15px;">Datos rápidos y bloques</h3>
                @foreach($quickItems as $key => $label)
                    <label style="display:flex;align-items:center;gap:8px;margin:9px 0;"><input type="hidden" name="dashboard_preferences[{{ $key }}]" value="0"><input type="checkbox" name="dashboard_preferences[{{ $key }}]" value="1" @checked($preferences[$key] ?? true) style="width:auto;">{{ $label }}</label>
                @endforeach
            </section>
            <section>
                <h3 style="font-size:15px;">Alertas</h3>
                @foreach($alertItems as $key => $label)
                    <label style="display:flex;align-items:center;gap:8px;margin:9px 0;"><input type="hidden" name="dashboard_preferences[{{ $key }}]" value="0"><input type="checkbox" name="dashboard_preferences[{{ $key }}]" value="1" @checked($preferences[$key] ?? true) style="width:auto;">{{ $label }}</label>
                @endforeach
            </section>
        </div>
        <div class="actions">
            <input name="totp_code" inputmode="numeric" maxlength="32" placeholder="Código de Google Authenticator" required style="max-width:290px;">
            <button class="btn" type="submit">Guardar visibilidad</button>
        </div>
    </form>
@endforeach
@endsection
