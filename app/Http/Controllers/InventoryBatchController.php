<?php

namespace App\Http\Controllers;

use App\Models\InventarioLote;
use App\Models\ConfiguracionEmpresa;
use App\Models\Producto;
use App\Services\AuditLogService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryBatchController extends Controller
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    public function index(Request $request)
    {
        $diasAlertaVencimiento = max(1, (int) (ConfiguracionEmpresa::firstOrCreate([])->dias_alerta_vencimiento ?? 30));

        $lotes = InventarioLote::with('producto', 'compra', 'usuario')
            ->withSum('consumos as cantidad_consumida', 'cantidad')
            ->when($request->input('estado', 'activo') !== 'todos', fn ($query) => $query->where('estado', $request->input('estado', 'activo')))
            ->when($request->filled('producto_id'), fn ($query) => $query->where('producto_id', $request->producto_id))
            ->when($request->filled('vencimiento'), function ($query) use ($request, $diasAlertaVencimiento) {
                return match ($request->vencimiento) {
                    'por_vencer' => $query
                        ->whereNotNull('fecha_vencimiento')
                        ->whereDate('fecha_vencimiento', '>=', today())
                        ->whereDate('fecha_vencimiento', '<=', today()->addDays($diasAlertaVencimiento)),
                    'vencidos' => $query
                        ->whereNotNull('fecha_vencimiento')
                        ->whereDate('fecha_vencimiento', '<', today()),
                    'vigentes' => $query
                        ->whereNotNull('fecha_vencimiento')
                        ->whereDate('fecha_vencimiento', '>', today()->addDays($diasAlertaVencimiento)),
                    'sin_fecha' => $query->whereNull('fecha_vencimiento'),
                    default => $query,
                };
            })
            ->orderByRaw('CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END')
            ->orderBy('fecha_vencimiento')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('inventario.lotes', [
            'lotes' => $lotes,
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(),
            'diasAlertaVencimiento' => $diasAlertaVencimiento,
        ]);
    }

    public function update(Request $request, InventarioLote $lote)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $cantidadConsumida = (float) $lote->consumos()->sum('cantidad');
        $maxDisponible = max(0, round((float) $lote->cantidad_inicial - $cantidadConsumida, 2));

        $data = $request->validate([
            'totp_code' => ['required', 'string', 'max:32'],
            'codigo_lote' => ['nullable', 'string', 'max:255'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'cantidad_disponible' => ['required', 'numeric', 'min:0', 'max:' . $maxDisponible],
            'estado' => ['required', 'in:activo,inactivo'],
            'observacion' => ['nullable', 'string'],
        ]);

        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'lotes', $lote, 'Intento de editar lote')) {
            return back()->withErrors(['totp_code' => $error]);
        }

        $before = $lote->only(['codigo_lote', 'fecha_vencimiento', 'cantidad_disponible', 'estado', 'observacion']);
        unset($data['totp_code']);
        $lote->update($data);
        $lote->detalleCompra?->update([
            'codigo_lote' => $data['codigo_lote'] ?? null,
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
        ]);
        $lote->consumos()->update([
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
        ]);
        app(AuditLogService::class)->record(
            $request,
            'actualizo',
            'lotes',
            $lote,
            $before,
            $lote->only(array_keys($before)),
            'Edicion protegida de lote y vencimiento'
        );

        return back()->with('ok', 'Lote actualizado.');
    }

    public function destroy(Request $request, InventarioLote $lote)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $request->validate(['totp_code' => ['required', 'string', 'max:32']]);
        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'lotes', $lote, 'Intento de retirar lote')) {
            return back()->withErrors(['totp_code' => $error]);
        }

        $before = $lote->only(['codigo_lote', 'fecha_vencimiento', 'cantidad_disponible', 'estado', 'observacion']);
        $tieneConsumos = $lote->consumos()->exists();
        $cantidadDisponible = round((float) $lote->cantidad_disponible, 2);
        $producto = $lote->producto()->firstOrFail();

        DB::transaction(function () use ($request, $lote, $producto, $cantidadDisponible, $tieneConsumos, $before) {
            if ($cantidadDisponible > 0) {
                // Retiring a lot must also remove its remaining units from the
                // product ledger; otherwise the product stock stays inflated.
                $this->inventory->registrarMovimiento(
                    $producto,
                    'ajuste_negativo',
                    $cantidadDisponible,
                    'lote',
                    $lote->id,
                    $request->user()->id,
                    'Existencia retirada al eliminar lote'
                );
            }

            if ($tieneConsumos) {
                // Preserve consumed-lot history while removing it from active availability.
                $lote->update([
                    'estado' => 'inactivo',
                    'cantidad_disponible' => 0,
                    'observacion' => trim(($lote->observacion ? $lote->observacion . "\n" : '') . 'Lote eliminado manualmente; se conserva por historial.'),
                ]);
            } else {
                $lote->delete();
            }

            app(AuditLogService::class)->record(
                $request,
                'elimino',
                'lotes',
                $lote,
                $before,
                $tieneConsumos ? $lote->only(array_keys($before)) : null,
                $tieneConsumos ? 'Lote retirado de disponibilidad; se conserva por historial.' : 'Lote eliminado definitivamente.'
            );
        });

        return back()->with('ok', $tieneConsumos
            ? 'Lote retirado de disponibilidad y conservado por historial.'
            : 'Lote eliminado definitivamente.');
    }
}
