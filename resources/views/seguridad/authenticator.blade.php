@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <h1>Seguridad del administrador</h1>
        <p>Proteja eliminaciones, anulaciones y cambios al historial con Google Authenticator.</p>
    </div>
    <a class="btn secondary" href="{{ route('seguridad.dashboard') }}">Visibilidad del dashboard</a>
</div>

@if ($recoveryCodes)
    <section class="card" style="max-width: 760px; margin-bottom: 1rem;">
        <h2>Códigos de recuperación</h2>
        <p>Guárdelos en un lugar seguro. Cada código funciona una sola vez y reemplaza temporalmente el código de la aplicación.</p>
        <div class="recovery-codes">
            @foreach ($recoveryCodes as $code)
                <code>{{ $code }}</code>
            @endforeach
        </div>
    </section>
@endif

<section class="card" style="max-width: 760px;">
    @if (! $setupSecret && ! $user->two_factor_confirmed_at)
        <h2>Activar Google Authenticator</h2>
        <p>Instale Google Authenticator, Microsoft Authenticator, Authy u otra aplicación compatible en su teléfono.</p>
        <form method="POST" action="{{ route('seguridad.authenticator.iniciar') }}">
            @csrf
            <button class="btn btn-primary" type="submit">Generar código QR</button>
        </form>
    @elseif ($setupSecret)
        <h2>{{ $user->two_factor_confirmed_at ? 'Cambiar autenticador' : 'Vincular autenticador' }}</h2>
        <p>Escanee el código QR desde su aplicación y escriba el código de seis dígitos que aparece allí.</p>
        <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; align-items: center; margin: 1rem 0;">
            <img src="{{ $qrCode }}" alt="Código QR para Google Authenticator" width="240" height="240">
            <div>
                <strong>Clave manual</strong>
                <p><code style="word-break: break-all;">{{ $setupSecret }}</code></p>
            </div>
        </div>
        <form method="POST" action="{{ route('seguridad.authenticator.confirmar') }}">
            @csrf
            @include('partials.totp-code')
            <button class="btn btn-primary" type="submit">Confirmar y activar</button>
        </form>
    @else
        <h2>Google Authenticator está activo</h2>
        <p>Las acciones sensibles requieren un código de seis dígitos. Después de cinco intentos fallidos, el acceso queda bloqueado durante 15 minutos.</p>
        <form method="POST" action="{{ route('seguridad.authenticator.reiniciar') }}">
            @csrf
            @include('partials.totp-code')
            <button class="btn btn-secondary" type="submit">Cambiar autenticador</button>
        </form>
    @endif
</section>
@endsection

@push('styles')
<style>
.recovery-codes { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.5rem; margin-top:1rem; }
.recovery-codes code { padding:.6rem; border:1px solid var(--border-color, #2e4a67); border-radius:4px; text-align:center; font-size:1rem; }
</style>
@endpush
