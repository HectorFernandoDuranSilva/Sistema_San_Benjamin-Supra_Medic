@extends('layouts.app')

@section('content')
<div class="top"><h1>Importar Excel</h1></div>
<div class="card">
<p class="muted">Se leeran las hojas reales del archivo: Productos, listProductos, Clientes y Proveedor. Antes de confirmar se mostrara un reporte de revision.</p>
<form method="post" enctype="multipart/form-data" action="{{ route('importar.preview') }}">@csrf<div class="field"><label>Archivo Excel</label><input type="file" name="excel" accept=".xls,.xlsx,.xlsm" required></div><button class="btn">Vista previa</button></form>
</div>
@endsection
