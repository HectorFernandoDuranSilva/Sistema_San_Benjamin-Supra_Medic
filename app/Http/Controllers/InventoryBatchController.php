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

        $lotes->getCollection()->transform(function (InventarioLote $lote): InventarioLote {
            $consumidoReal = (float) ($lote->cantidad_consumida ?? 0);
            $lote->cantidad_consumida_real = $consumidoReal;
            $lote->cantidad_consumida = round($consumidoReal + (float) ($lote->cantidad_consumida_ajustada ?? 0), 2);

            return $lote;
        });

        return view('inventario.lotes', [
            'lotes' => $lotes,
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(),
            'diasAlertaVencimiento' => $diasAlertaVencimiento,
        ]);
    }

    public function update(Request $request, InventarioLote $lote)
    {
        $data = $request->validate([
            'codigo_lote' => ['nullable', 'string', 'max:255'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'cantidad_inicial' => ['required', 'numeric', 'min:0'],
            'cantidad_consumida' => ['required', 'numeric', 'min:0'],
            'cantidad_disponible' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', 'in:activo,inactivo'],
            'observacion' => ['nullable', 'string'],
        ]);

        $cantidadInicial = round((float) $data['cantidad_inicial'], 2);
        $cantidadConsumida = round((float) $data['cantidad_consumida'], 2);
        $cantidadDisponible = round((float) $data['cantidad_disponible'], 2);
        $disponibleCalculada = round($cantidadInicial - $cantidadConsumida, 2);

        if (abs($disponibleCalculada - $cantidadDisponible) > 0.01) {
            return back()->withErrors(['cantidad_disponible' => 'Inicial - consumido debe coincidir con disponible.'])->withInput();
        }

        $before = $lote->only(['codigo_lote', 'fecha_vencimiento', 'cantidad_inicial', 'cantidad_disponible', 'cantidad_consumida_ajustada', 'estado', 'observacion']);
        try {
            DB::transaction(function () use ($request, $lote, $data, $before, $cantidadInicial, $cantidadConsumida, $cantidadDisponible): void {
                $lote = InventarioLote::whereKey($lote->id)->lockForUpdate()->firstOrFail();
                $cantidadConsumidaReal = (float) $lote->consumos()->sum('cantidad');
                if ($cantidadConsumida + 0.0001 < $cantidadConsumidaReal) {
                    throw new \InvalidArgumentException('El consumido no puede ser menor al consumo histórico real del lote.');
                }
                $diferenciaStock = round($cantidadDisponible - (float) $lote->cantidad_disponible, 2);

                if ($diferenciaStock > 0) {
                    $this->inventory->registrarMovimiento(
                        $lote->producto,
                        'ajuste_positivo',
                        $diferenciaStock,
                        'lote',
                        $lote->id,
                        $request->user()->id,
                        'Ajuste de saldo de lote'
                    );
                } elseif ($diferenciaStock < 0) {
                    $this->inventory->registrarMovimiento(
                        $lote->producto,
                        'ajuste_negativo',
                        abs($diferenciaStock),
                        'lote',
                        $lote->id,
                        $request->user()->id,
                        'Ajuste de saldo de lote'
                    );
                }

                $update = collect($data)->except(['cantidad_inicial', 'cantidad_consumida', 'cantidad_disponible'])->all();
                $update['cantidad_inicial'] = $cantidadInicial;
                $update['cantidad_disponible'] = $cantidadDisponible;
                $update['cantidad_consumida_ajustada'] = round($cantidadConsumida - $cantidadConsumidaReal, 2);
                $lote->update($update);
            $lote->detalleCompra?->update([
                'codigo_lote' => $data['codigo_lote'] ?? null,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
            ]);
            $lote->consumos()->update([
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
            ]);
            $this->inventory->sincronizarStockDesdeLotes(
                $lote->producto,
                $request->user()->id,
                'Saldo de producto reconciliado con lotes editados'
            );
            app(AuditLogService::class)->record(
                $request,
                'actualizo',
                'lotes',
                $lote,
                $before,
                $lote->fresh()->only(array_keys($before)),
                'Edición de lote y vencimiento'
            );
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['cantidad_disponible' => $e->getMessage()])->withInput();
        }

        return back()->with('ok', 'Lote actualizado.');
    }

    public function darDeBajaVencido(Request $request, InventarioLote $lote)
    {
        $usuario = $request->user();

        try {
            DB::transaction(function () use ($request, $lote, $usuario): void {
                $lote = InventarioLote::with('producto')
                    ->whereKey($lote->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $lote->fecha_vencimiento || ! $lote->fecha_vencimiento->lt(today())) {
                    throw new \InvalidArgumentException('Solo se pueden dar de baja lotes ya vencidos.');
                }

                $cantidad = round((float) $lote->cantidad_disponible, 2);
                if ($cantidad <= 0 || $lote->estado !== 'activo') {
                    throw new \InvalidArgumentException('El lote no tiene existencia disponible para dar de baja.');
                }

                $before = $lote->only(['codigo_lote', 'fecha_vencimiento', 'cantidad_disponible', 'estado', 'observacion']);
                $this->inventory->registrarMovimiento(
                    $lote->producto,
                    'ajuste_negativo',
                    $cantidad,
                    'lote_vencido',
                    $lote->id,
                    $usuario->id,
                    'Baja de existencia por vencimiento'
                );

                $observacion = trim(($lote->observacion ? $lote->observacion . "\n" : '') . 'Baja por vencimiento: ' . now()->format('d/m/Y H:i'));
                $lote->update([
                    'cantidad_disponible' => 0,
                    'estado' => 'inactivo',
                    'observacion' => $observacion,
                ]);

                app(AuditLogService::class)->record(
                    $request,
                    'actualizo',
                    'lotes',
                    $lote,
                    $before,
                    $lote->fresh()->only(array_keys($before)),
                    'Baja de lote vencido; existencia retirada del inventario.'
                );
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['lote' => $e->getMessage()]);
        }

        return back()->with('ok', 'Lote vencido dado de baja y existencia retirada.');
    }

    public function destroy(Request $request, InventarioLote $lote)
    {
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
