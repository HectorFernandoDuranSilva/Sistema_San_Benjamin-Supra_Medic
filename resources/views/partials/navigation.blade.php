@php
    $user = auth()->user();
    $operationActive = request()->routeIs('ventas.*') || request()->routeIs('compras.*') || request()->routeIs('cotizaciones.*');
    $inventoryActive = request()->routeIs('inventario.*') || request()->routeIs('productos.*') || request()->routeIs('categorias.*') || request()->routeIs('reportes.kardex*');
    $reportsActive = request()->routeIs('notas.*') || (request()->routeIs('reportes.*') && ! request()->routeIs('reportes.kardex*'));
    $adminActive = request()->routeIs('clientes.*') || request()->routeIs('proveedores.*') || request()->routeIs('usuarios.*') || request()->routeIs('seguridad.*');
@endphp

<nav class="glass-dock">
    @if($user->hasPermission('dashboard'))
        <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
    @endif

    @if($user->hasPermission('ventas') || $user->hasPermission('compras') || $user->hasPermission('cotizaciones'))
        <div class="dock-group">
            <button type="button" class="dock-trigger {{ $operationActive ? 'active' : '' }}">Operación</button>
            <div class="dock-menu">
                @if($user->hasPermission('ventas'))<a class="{{ request()->routeIs('ventas.*') ? 'active' : '' }}" href="{{ route('ventas.index') }}">Ventas</a>@endif
                @if($user->hasPermission('compras'))<a class="{{ request()->routeIs('compras.*') ? 'active' : '' }}" href="{{ route('compras.index') }}">Compras</a>@endif
                @if($user->hasPermission('cotizaciones'))<a class="{{ request()->routeIs('cotizaciones.*') ? 'active' : '' }}" href="{{ route('cotizaciones.index') }}">Cotizaciones</a>@endif
            </div>
        </div>
    @endif

    @if($user->hasPermission('inventario') || $user->hasPermission('productos') || ($user->hasPermission('reportes') && $user->hasPermission('kardex')))
        <div class="dock-group">
            <button type="button" class="dock-trigger {{ $inventoryActive ? 'active' : '' }}">Inventario</button>
            <div class="dock-menu">
                @if($user->hasPermission('inventario'))<a class="{{ request()->routeIs('inventario.*') ? 'active' : '' }}" href="{{ route('inventario.index') }}">Inventario</a>@endif
                @if($user->hasPermission('productos'))<a class="{{ request()->routeIs('productos.*') ? 'active' : '' }}" href="{{ route('productos.index') }}">Productos</a>@endif
                @if($user->hasPermission('productos'))<a class="{{ request()->routeIs('categorias.*') ? 'active' : '' }}" href="{{ route('categorias.index') }}">Categorías</a>@endif
                @if($user->hasPermission('reportes') && $user->hasPermission('kardex'))<a class="{{ request()->routeIs('reportes.kardex*') ? 'active' : '' }}" href="{{ route('reportes.kardex') }}">Kardex</a>@endif
            </div>
        </div>
    @endif

    @if($user->hasPermission('reportes'))
        <div class="dock-group">
            <button type="button" class="dock-trigger {{ $reportsActive ? 'active' : '' }}">Reportes</button>
            <div class="dock-menu">
                <a class="{{ request()->routeIs('reportes.*') && ! request()->routeIs('reportes.documentos*') && ! request()->routeIs('reportes.balance-mensual*') && ! request()->routeIs('reportes.kardex*') ? 'active' : '' }}" href="{{ route('reportes.ventas') }}">Ventas</a>
                @if($user->hasPermission('notas_facturas'))<a class="{{ request()->routeIs('reportes.documentos*') ? 'active' : '' }}" href="{{ route('reportes.documentos') }}">Notas/Facturas</a>@endif
                @if($user->hasPermission('balance_mensual'))<a class="{{ request()->routeIs('reportes.balance-mensual*') ? 'active' : '' }}" href="{{ route('reportes.balance-mensual') }}">Balance mensual</a>@endif
            </div>
        </div>
    @endif

    @if($user->hasPermission('clientes') || $user->hasPermission('proveedores') || $user->hasPermission('configuracion') || ($user->isAdmin() && ($user->hasPermission('usuarios') || $user->hasPermission('seguridad'))))
        <div class="dock-group">
            <button type="button" class="dock-trigger {{ $adminActive ? 'active' : '' }}">Administración</button>
            <div class="dock-menu">
                @if($user->hasPermission('clientes'))<a class="{{ request()->routeIs('clientes.*') ? 'active' : '' }}" href="{{ route('clientes.index') }}">Clientes</a>@endif
                @if($user->hasPermission('proveedores'))<a class="{{ request()->routeIs('proveedores.*') ? 'active' : '' }}" href="{{ route('proveedores.index') }}">Proveedores</a>@endif
                @if($user->hasPermission('configuracion'))<a class="{{ request()->routeIs('configuracion.*') ? 'active' : '' }}" href="{{ route('configuracion.edit') }}">Configuración</a>@endif
                @if($user->isAdmin() && $user->hasPermission('usuarios'))<a class="{{ request()->routeIs('usuarios.*') ? 'active' : '' }}" href="{{ route('usuarios.index') }}">Usuarios</a>@endif
                @if($user->isAdmin() && $user->hasPermission('seguridad'))<a class="{{ request()->routeIs('seguridad.*') ? 'active' : '' }}" href="{{ route('seguridad.authenticator') }}">Seguridad</a>@endif
            </div>
        </div>
    @endif

    <form method="post" action="{{ route('logout') }}" style="margin:0;">@csrf<button>Cerrar sesión</button></form>
</nav>
