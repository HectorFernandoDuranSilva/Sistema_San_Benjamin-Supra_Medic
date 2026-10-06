<?php

namespace App\Http\Controllers;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'stock_desde' => ['nullable', 'numeric', 'min:0'],
            'stock_hasta' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (isset($filters['stock_desde'], $filters['stock_hasta'])
            && (float) $filters['stock_desde'] > (float) $filters['stock_hasta']) {
            return back()
                ->withInput()
                ->withErrors(['stock_hasta' => 'El stock máximo debe ser igual o mayor al stock mínimo.']);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        $ultimoStock = 'CAST(COALESCE((SELECT stock_nuevo FROM movimientos_inventario '
            . 'WHERE movimientos_inventario.producto_id = productos.id '
            . 'ORDER BY fecha_movimiento DESC, id DESC LIMIT 1), 0) AS REAL)';
        $productos = Producto::query()
            ->where('activo', true)
            ->when($q !== '', fn ($query) => $query->where(function ($query) use ($q) {
                $query->where('codigo', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%");
            }))
            ->when(isset($filters['stock_desde']), fn ($query) => $query->whereRaw("{$ultimoStock} >= ?", [(float) $filters['stock_desde']]))
            ->when(isset($filters['stock_hasta']), fn ($query) => $query->whereRaw("{$ultimoStock} <= ?", [(float) $filters['stock_hasta']]))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('inventario.index', compact('productos', 'filters'));
    }

    public function movimientos(Request $request)
    {
        $movimientos = MovimientoInventario::with('producto', 'usuario')
            ->when($request->filled('producto_id'), fn ($query) => $query->where('producto_id', $request->producto_id))
            ->when($request->filled('tipo_movimiento'), fn ($query) => $query->where('tipo_movimiento', $request->tipo_movimiento))
            ->latest('fecha_movimiento')
            ->paginate(30)
            ->withQueryString();

        return view('inventario.movimientos', [
            'movimientos' => $movimientos,
            'productos' => Producto::orderBy('nombre')->get(),
        ]);
    }

    public function ajustar(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'producto_id' => ['required', 'exists:productos,id'],
            'tipo_movimiento' => ['required', 'in:ajuste_positivo,ajuste_negativo'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'observacion' => ['nullable', 'string'],
        ]);

        $inventory->registrarMovimiento(
            Producto::findOrFail($data['producto_id']),
            $data['tipo_movimiento'],
            (float) $data['cantidad'],
            'ajuste_manual',
            null,
            $request->user()->id,
            $data['observacion'] ?? null
        );

        return back()->with('ok', 'Movimiento registrado.');
    }
}
