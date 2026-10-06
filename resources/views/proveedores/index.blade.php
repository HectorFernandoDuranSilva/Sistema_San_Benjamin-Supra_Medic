@extends('layouts.app')

@section('content')
<div class="top"><h1>Proveedores</h1></div>
<form class="card" method="post" action="{{ route('proveedores.store') }}" style="margin-bottom:16px;">@csrf
<div class="row"><div class="field"><label>Nombre</label><input name="nombre" required></div><div class="field"><label>Documento</label><input name="documento"></div><div class="field"><label>Telefono</label><input name="telefono"></div><div class="field"><label>Direccion</label><input name="direccion"></div></div><button class="btn">Crear</button></form>
<form class="card" method="get" style="margin-bottom:16px;">
<div class="row"><div class="field"><label>Buscar proveedor</label><input name="buscar" value="{{ $buscar }}" placeholder="Nombre, NIT, teléfono o dirección"></div><div class="field" style="align-self:end;"><button class="btn secondary" type="submit">Buscar</button></div></div>
</form>
<div class="card"><table><tr><th>Nombre</th><th>Documento</th><th>Telefono</th><th>Direccion</th><th>Activo</th><th></th></tr>
@foreach($proveedores as $p)<tr><form method="post" action="{{ route('proveedores.update', $p) }}">@csrf @method('put')<td><input name="nombre" value="{{ $p->nombre }}"></td><td><input name="documento" value="{{ $p->documento }}"></td><td><input name="telefono" value="{{ $p->telefono }}"></td><td><input name="direccion" value="{{ $p->direccion }}"></td><td><input style="width:auto;" type="checkbox" name="activo" value="1" @checked($p->activo)></td><td><button class="btn secondary">Guardar</button></td></form></tr>@endforeach
</table>{{ $proveedores->links() }}</div>
@endsection
