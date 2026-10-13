<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return view('categorias.index', ['categorias' => Categoria::orderBy('nombre')->paginate(20)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:categorias,nombre'],
            'descripcion' => ['nullable', 'string'],
        ]);
        Categoria::create($data + ['activo' => true]);

        return back()->with('ok', 'Categoria creada.');
    }

    public function update(Request $request, Categoria $categoria)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:categorias,nombre,' . $categoria->id],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $categoria->update($data + ['activo' => $request->boolean('activo')]);

        return back()->with('ok', 'Categoria actualizada.');
    }

    public function destroy(Request $request, Categoria $categoria)
    {
        $request->validate(['totp_code' => ['required', 'string', 'max:32']]);
        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'categorias', $categoria, 'Intento de eliminar categoría')) {
            return back()->withErrors(['totp_code' => $error]);
        }

        if ($categoria->productos()->exists()) {
            $categoria->update(['activo' => false]);

            return back()->with('ok', 'Categoria desactivada porque tiene productos asociados.');
        }

        $categoria->delete();

        return back()->with('ok', 'Categoria eliminada.');
    }
}
