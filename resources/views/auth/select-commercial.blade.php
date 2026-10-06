@extends('layouts.app')

@section('content')
<section class="login-shell">
    <div class="glass-login">
        <form id="commercial-form" class="glass-form" method="post" action="{{ route('commercial.select.store') }}">
            @csrf
            <h1>Nombre comercial</h1>
            <p>Seleccione con que nombre comercial iniciara el dashboard.</p>

            <div class="exchange-box">
                <span>Tipo de cambio del dia</span>
                <strong>Bs {{ number_format($tipoCambioValor, 2) }}</strong>
            </div>

            <div class="selection-options">
                @foreach($commercialOptions as $option)
                    <label class="selection-option">
                        <input type="radio" name="commercial_name" value="{{ $option['key'] }}" data-name="{{ $option['name'] }}" required @checked($loop->first)>
                        <img src="{{ asset($option['logo']) }}" alt="{{ $option['name'] }}">
                        <strong>{{ $option['name'] }}</strong>
                        <span>NIT: {{ $option['nit'] }}</span>
                    </label>
                @endforeach
            </div>

            <button type="submit" style="width:100%;">Continuar</button>
        </form>
    </div>
</section>

<div id="glass-alert" class="glass-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="glass-alert-title">
    <div class="glass-modal">
        <h2 id="glass-alert-title">Seleccione nombre comercial</h2>
        <p id="glass-alert-message">Antes de abrir el dashboard, seleccione con qué nombre comercial iniciará.</p>
        <div class="glass-modal-actions">
            <button type="button" class="btn" id="glass-alert-ok">Entendido</button>
        </div>
    </div>
</div>

<div id="glass-confirm" class="glass-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="glass-confirm-title">
    <div class="glass-modal">
        <h2 id="glass-confirm-title">Confirmar selección</h2>
        <p id="glass-confirm-message"></p>
        <div class="glass-modal-actions">
            <button type="button" class="btn secondary" id="glass-confirm-cancel">Cancelar</button>
            <button type="button" class="btn" id="glass-confirm-ok">Continuar</button>
        </div>
    </div>
</div>

<script>
const alertModal = document.getElementById('glass-alert');
const confirmModal = document.getElementById('glass-confirm');
const form = document.getElementById('commercial-form');
let confirmedSubmit = false;

function openGlassModal(modal) {
    modal.classList.add('is-open');
}

function closeGlassModal(modal) {
    modal.classList.remove('is-open');
}

window.addEventListener('DOMContentLoaded', function () {
    openGlassModal(alertModal);
});

document.getElementById('glass-alert-ok').addEventListener('click', function () {
    closeGlassModal(alertModal);
});

document.getElementById('glass-confirm-cancel').addEventListener('click', function () {
    closeGlassModal(confirmModal);
});

document.getElementById('glass-confirm-ok').addEventListener('click', function () {
    confirmedSubmit = true;
    closeGlassModal(confirmModal);
    form.submit();
});

form.addEventListener('submit', function (event) {
    if (confirmedSubmit) {
        return;
    }

    event.preventDefault();

    const selected = this.querySelector('input[name="commercial_name"]:checked');
    const name = selected ? selected.dataset.name : '';
    document.getElementById('glass-confirm-message').textContent = 'Iniciará con el nombre comercial ' + name + '. Se usará este nombre y su NIT para el dashboard y operaciones nuevas. ¿Desea continuar?';
    openGlassModal(confirmModal);
});
</script>
@endsection
