@extends('layouts.app')

@section('content')
<section class="login-shell">
    <div class="glass-login">
        <img class="login-logo left" src="{{ asset('assets/logos/san-benjamin.png') }}" alt="San Benjamin">
        <img class="login-logo right" src="{{ asset('assets/logos/supra-medic.png') }}" alt="Supra Medic">

        <form class="glass-form" method="post" action="{{ route('login.store') }}">
            @csrf
            <h1>Bienvenido</h1>
            <p>Acceso privado al sistema.</p>

            <div class="theme-switch-row">
                <span>Modo oscuro</span>
                <label class="theme-toggle" aria-label="Cambiar modo oscuro">
                    <input type="checkbox" data-theme-toggle>
                    <span class="theme-toggle-track"><span class="theme-toggle-knob"></span></span>
                </label>
            </div>

            <div class="exchange-box">
                <span>Tipo de cambio del dia</span>
                <strong>Bs {{ number_format($tipoCambioValor, 2) }}</strong>
            </div>

            <div class="field">
                <label>Usuario</label>
                <input type="text" name="username" value="{{ old('username') }}" autocomplete="username" required autofocus>
            </div>
            <div class="field">
                <label>Contrasena</label>
                <input type="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" style="width:100%;">Entrar</button>
        </form>
    </div>
</section>
@endsection
