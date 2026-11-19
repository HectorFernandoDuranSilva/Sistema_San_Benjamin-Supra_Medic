@if(session('ok'))
    <div class="glass-modal-backdrop is-open" role="dialog" aria-modal="true">
        <div class="glass-modal is-success">
            <h2>Operación realizada</h2>
            <p>{{ session('ok') }}</p>
            <div class="glass-modal-actions">
                <button type="button" class="btn" data-glass-close>Entendido</button>
            </div>
        </div>
    </div>
@endif

@php
    $flashErrors = ($errors ?? null)?->getMessages() ?? [];
    if (request()->routeIs('compras.create')) {
        unset($flashErrors['venta']);
    }
@endphp

@if($flashErrors)
    <div class="glass-modal-backdrop is-open" role="dialog" aria-modal="true">
        <div class="glass-modal is-error">
            <h2>Revise la información</h2>
            <ul class="glass-modal-list">
                @foreach($flashErrors as $messages)
                    @foreach($messages as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                @endforeach
            </ul>
            <div class="glass-modal-actions">
                <button type="button" class="btn" data-glass-close>Entendido</button>
            </div>
        </div>
    </div>
@endif
