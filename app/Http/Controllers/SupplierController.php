<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $buscar = trim((string) $request->query('buscar', ''));

        $proveedores = Proveedor::query()
            ->when($buscar !== '', function ($query) use ($buscar) {
                $termino = '%' . $buscar . '%';

                $query->where(function ($query) use ($termino) {
                    $query->where('nombre', 'like', $termino)
                        ->orWhere('documento', 'like', $termino)
                        ->orWhere('telefono', 'like', $termino)
                        ->orWhere('direccion', 'like', $termino);
                });
            })
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('proveedores.index', compact('proveedores', 'buscar'));
    }

    public function store(Request $request)
    {
        Proveedor::create($this->validated($request));

        return back()->with('ok', 'Proveedor creado.');
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $proveedor->update($this->validated($request) + ['activo' => $request->boolean('activo')]);

        return back()->with('ok', 'Proveedor actualizado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ]);
    }
}
