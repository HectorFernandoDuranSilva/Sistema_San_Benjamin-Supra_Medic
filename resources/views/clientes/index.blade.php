@extends('layouts.app')

@section('content')
<div class="top"><h1>Clientes</h1></div>

<form class="card" method="post" action="{{ route('clientes.store') }}" style="margin-bottom:16px;">
    @csrf
    <div class="row">
        <div class="field"><label>Nombre</label><input name="nombre" required></div>
        <div class="field"><label>Razón social</label><input name="razon_social"></div>
        <div class="field"><label>NIT</label><input name="documento" placeholder="NIT del cliente"></div>
        <div class="field"><label>Telefono</label><input name="telefono"></div>
        <div class="field"><label>Direccion</label><input name="direccion"></div>
    </div>
    <button class="btn">Crear</button>
</form>

<form class="card" method="get" style="margin-bottom:16px;">
    <div class="row">
        <div class="field"><label>Buscar cliente</label><input name="buscar" value="{{ $buscar }}" placeholder="Nombre, razón social o NIT"></div>
        <div class="field" style="align-self:end;"><button class="btn" type="submit">Buscar</button></div>
    </div>
</form>

<div class="card">
    <table>
        <tr><th>Nombre</th><th>Razón social</th><th>NIT</th><th>Telefono</th><th>Direccion</th><th>Activo</th><th>Acciones</th></tr>
        @foreach($clientes as $c)
            <tr>
                <td><input form="cliente-update-{{ $c->id }}" name="nombre" value="{{ $c->nombre }}"></td>
                <td><input form="cliente-update-{{ $c->id }}" name="razon_social" value="{{ $c->razon_social }}"></td>
                <td><input form="cliente-update-{{ $c->id }}" name="documento" value="{{ $c->documento }}" placeholder="NIT del cliente"></td>
                <td><input form="cliente-update-{{ $c->id }}" name="telefono" value="{{ $c->telefono }}"></td>
                <td><input form="cliente-update-{{ $c->id }}" name="direccion" value="{{ $c->direccion }}"></td>
                <td><input form="cliente-update-{{ $c->id }}" style="width:auto;" type="checkbox" name="activo" value="1" @checked($c->activo)></td>
                <td>
                    <div class="actions">
                        <form id="cliente-update-{{ $c->id }}" method="post" action="{{ route('clientes.update', $c) }}">
                            @csrf
                            @method('put')
                            <button class="btn secondary">Guardar</button>
                        </form>
                        <form method="post" action="{{ route('clientes.destroy', $c) }}" data-glass-confirm="¿Desea eliminar este cliente? Si tiene ventas asociadas se desactivará.">
                            @csrf
                            @method('delete')
                            <input name="totp_code" inputmode="numeric" maxlength="32" placeholder="Código Authenticator" required>
                            <button class="btn danger">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
    </table>
    {{ $clientes->links() }}
</div>
@endsection
