<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionEmpresa;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        $empresa = ConfiguracionEmpresa::firstOrCreate([]);

        return view('configuracion.edit', [
            'empresa' => $empresa,
            'nombreComercial' => session('commercial_context.name', $empresa->nombre_negocio),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'moneda' => ['required', 'string', 'max:10'],
            'texto_pie_recibos' => ['nullable', 'string'],
            'tamano_papel_recibo' => ['required', 'string', 'max:50'],
            'dias_alerta_vencimiento' => ['required', 'integer', 'min:1', 'max:365'],
            'dias_alerta_cuotas' => ['required', 'integer', 'min:1', 'max:365'],
            'dashboard_opciones' => ['nullable', 'array'],
            'dashboard_opciones.*' => ['boolean'],
            'dashboard_opciones_por_rol' => ['nullable', 'array'],
            'dashboard_opciones_por_rol.administrador' => ['nullable', 'array'],
            'dashboard_opciones_por_rol.operador' => ['nullable', 'array'],
            'dashboard_opciones_por_rol.*.*' => ['boolean'],
        ]);

        ConfiguracionEmpresa::firstOrCreate([])->update($data);

        return back()->with('ok', 'Configuración guardada.');
    }
}
