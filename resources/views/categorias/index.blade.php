@extends('layouts.app')

@section('content')
<div class="top"><h1>Categorias</h1></div>

<form class="card" method="post" action="{{ route('categorias.store') }}" style="margin-bottom:16px;">
    @csrf
    <div class="row">
        <div class="field"><label>Nombre</label><input name="nombre" required></div>
        <div class="field"><label>Descripcion</label><input name="descripcion"></div>
    </div>
    <button class="btn">Crear</button>
</form>

<div class="card">
    <table>
        <tr><th>Nombre</th><th>Descripcion</th><th>Activo</th><th>Acciones</th></tr>
        @foreach($categorias as $c)
            <tr>
                <td><input form="categoria-update-{{ $c->id }}" name="nombre" value="{{ $c->nombre }}"></td>
                <td><input form="categoria-update-{{ $c->id }}" name="descripcion" value="{{ $c->descripcion }}"></td>
                <td><input form="categoria-update-{{ $c->id }}" style="width:auto;" type="checkbox" name="activo" value="1" @checked($c->activo)></td>
                <td>
                    <div class="actions">
                        <form id="categoria-update-{{ $c->id }}" method="post" action="{{ route('categorias.update', $c) }}">
                            @csrf
                            @method('put')
                            <button class="btn secondary">Guardar</button>
                        </form>
                        <form method="post" action="{{ route('categorias.destroy', $c) }}" data-glass-confirm="¿Desea eliminar esta categoría? Si tiene productos asociados se desactivará.">
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
    {{ $categorias->links() }}
</div>
@endsection
