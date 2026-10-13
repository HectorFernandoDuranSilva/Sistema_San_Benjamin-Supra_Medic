<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $buscar = trim((string) $request->query('buscar', ''));

        $clientes = Cliente::query()
            ->when($buscar !== '', fn ($query) => $query->coincideConBusqueda($buscar))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('clientes.index', compact('clientes', 'buscar'));
    }

    public function store(Request $request)
    {
        Cliente::create($this->validated($request));

        return back()->with('ok', 'Cliente creado.');
    }

    public function update(Request $request, Cliente $cliente)
    {
        $cliente->update($this->validated($request) + ['activo' => $request->boolean('activo')]);

        return back()->with('ok', 'Cliente actualizado.');
    }

    public function destroy(Request $request, Cliente $cliente)
    {
        $request->validate(['totp_code' => ['required', 'string', 'max:32']]);
        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'clientes', $cliente, 'Intento de eliminar cliente')) {
            return back()->withErrors(['totp_code' => $error]);
        }

        if ($cliente->ventas()->exists()) {
            $cliente->update(['activo' => false]);

            return back()->with('ok', 'Cliente desactivado porque tiene ventas asociadas.');
        }

        $cliente->delete();

        return back()->with('ok', 'Cliente eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ]);
    }
}
