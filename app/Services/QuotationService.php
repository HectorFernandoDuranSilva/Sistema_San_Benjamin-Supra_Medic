<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\InventarioLote;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationService
{
    public function __construct(private readonly SaleService $sales)
    {
    }

    public function registrar(array $data, int $usuarioId): Cotizacion
    {
        return $this->guardar($data, $usuarioId);
    }

    public function actualizar(Cotizacion $cotizacion, array $data, int $usuarioId): Cotizacion
    {
        return $this->guardar($data, $usuarioId, $cotizacion);
    }

    private function guardar(array $data, int $usuarioId, ?Cotizacion $existente = null): Cotizacion
    {
        $items = collect($data['items'] ?? []);

        if ($items->isEmpty()) {
            throw new InvalidArgumentException('La cotización debe tener al menos un producto.');
        }

        return DB::transaction(function () use ($data, $items, $usuarioId, $existente) {
            $anteriores = collect();
            if ($existente) {
                $existente = Cotizacion::whereKey($existente->id)->lockForUpdate()->firstOrFail();
                if ($existente->venta_id || $existente->estado === 'convertida') {
                    throw new InvalidArgumentException('La cotización ya fue convertida. No se puede modificar la venta vinculada.');
                }
                if ((int) ($data['revision'] ?? 0) !== (int) $existente->revision) {
                    throw new InvalidArgumentException('Otra persona modificó esta cotización. Vuelva a abrirla antes de guardar.');
                }
                $anteriores = $existente->detalles()->get()->keyBy('id');
            }
            $subtotal = 0;
            $descuentoTotal = 0;
            $impuestoTotal = 0;
            $total = 0;
            $descuentoPorcentaje = (float) ($data['descuento_porcentaje'] ?? 0);
            $productos = Producto::whereIn('id', $items->pluck('producto_id'))->get()->keyBy('id');
            $lotes = InventarioLote::whereIn('id', $items->pluck('inventario_lote_id')->filter())->get()->keyBy('id');

            foreach ($items as $item) {
                $producto = $productos->get((int) ($item['producto_id'] ?? 0));
                $porPedido = empty($item['producto_id']) && !empty($item['por_pedido']);
                $cantidad = (float) $item['cantidad'];
                $precio = (float) $item['precio_unitario'];
                $porcentaje = (float) $item['porcentaje_utilidad'];

                if ((!$producto && !$porPedido) || ($porPedido && trim((string) ($item['nombre_producto'] ?? '')) === '') || $cantidad <= 0 || $precio < 0 || $porcentaje < 25 || $porcentaje > 30) {
                    throw new InvalidArgumentException('Detalle de cotización inválido.');
                }
                if (!empty($item['id']) && (!$anteriores->has($item['id']) || ($anteriores[$item['id']]->producto_id !== null && $anteriores[$item['id']]->producto_id != $producto?->id))) {
                    throw new InvalidArgumentException('El ítem no corresponde a esta cotización.');
                }
                if (!empty($item['inventario_lote_id'])) {
                    $lote = $lotes->get((int) $item['inventario_lote_id']);
                    if (!$producto || !$lote || $lote->producto_id !== $producto->id) {
                        throw new InvalidArgumentException('El lote no corresponde al producto cotizado.');
                    }
                }

                $lineaSubtotal = $cantidad * $precio;
                $descuento = $this->lineAmount($item, 'descuento', round($lineaSubtotal * ($descuentoPorcentaje / 100), 2));
                $impuesto = $this->lineAmount($item, 'impuesto');
                $lineaTotal = max(0, $lineaSubtotal - $descuento) + $impuesto;

                $subtotal += $lineaSubtotal;
                $descuentoTotal += $descuento;
                $impuestoTotal += $impuesto;
                $total += $lineaTotal;
            }

            $footer = $this->footerDefaults($data['nombre_comercial'] ?? $existente?->nombre_comercial);
            $attributes = [
                'numero_cotizacion' => $existente?->numero_cotizacion ?? ($data['numero_cotizacion'] ?: $this->siguienteNumero()),
                'nombre_comercial' => $data['nombre_comercial'] ?? null,
                'cliente_id' => $data['cliente_id'] ?? null,
                'fecha' => $data['fecha'],
                'valida_hasta' => $data['valida_hasta'] ?? null,
                'nit_cliente' => $data['nit_cliente'] ?? null,
                'subtotal' => $subtotal,
                'descuento_porcentaje' => $descuentoPorcentaje,
                'descuento' => $descuentoTotal,
                'impuesto_total' => $impuestoTotal,
                'total' => max(0, $total),
                'estado' => 'emitida',
                'observacion' => $data['observacion'] ?? null,
                'footer_direccion' => $this->textOrDefault($data['footer_direccion'] ?? null, $footer['direccion']),
                'footer_validez' => $this->textOrDefault($data['footer_validez'] ?? null, $footer['validez']),
                'footer_entrega' => $this->textOrDefault($data['footer_entrega'] ?? null, $footer['entrega']),
                'footer_condiciones_pago' => $this->textOrDefault($data['footer_condiciones_pago'] ?? null, $footer['condiciones_pago']),
                'footer_deposito_cuenta' => $this->textOrDefault($data['footer_deposito_cuenta'] ?? null, $footer['deposito_cuenta']),
                'usuario_id' => $existente?->usuario_id ?? $usuarioId,
            ];
            if ($existente) {
                // A modified quotation must be checked again before it is sent.
                $attributes += ['confirmada_at' => null, 'confirmada_por_id' => null];
            }
            $cotizacion = $existente ?? new Cotizacion();
            $cotizacion->fill($attributes);
            $cotizacion->revision = $existente ? (int) $existente->revision + 1 : 1;
            $cotizacion->save();
            if ($existente) {
                $cotizacion->detalles()->delete();
            }

            foreach ($items as $item) {
                $producto = $productos->get((int) ($item['producto_id'] ?? 0));
                $cantidad = (float) $item['cantidad'];
                $precio = (float) $item['precio_unitario'];
                $porcentaje = (float) $item['porcentaje_utilidad'];
                $lineaSubtotal = $cantidad * $precio;
                $descuento = $this->lineAmount($item, 'descuento', round($lineaSubtotal * ($descuentoPorcentaje / 100), 2));
                $impuesto = $this->lineAmount($item, 'impuesto');

                $anterior = !empty($item['id']) ? $anteriores->get($item['id']) : null;
                $lote = $lotes->get((int) ($item['inventario_lote_id'] ?? 0));
                // Store the offered values, never a live reference to catalog data.
                $metadata = [];
                foreach (['marca', 'procedencia', 'fecha_vencimiento', 'inventario_lote_id'] as $field) {
                    $default = $anterior ? $anterior->{$field} : match ($field) {
                        'marca', 'procedencia' => $producto?->{$field},
                        'fecha_vencimiento' => $lote?->fecha_vencimiento,
                        'inventario_lote_id' => $lote?->id,
                    };
                    $metadata[$field] = array_key_exists($field, $item) ? ($item[$field] ?: null) : $default;
                }
                $cotizacion->detalles()->create([
                    ...$metadata,
                    'producto_id' => $producto?->id,
                    'codigo_producto' => $producto?->codigo ?? '',
                    'nombre_producto' => $producto?->nombre ?? trim($item['nombre_producto']),
                    'medida' => $producto?->medida ?? ($item['medida'] ?? null),
                    'cantidad' => $cantidad,
                    'costo_referencial' => $anterior?->producto_id ? (float) $anterior->costo_referencial : (float) ($producto?->costo_promedio ?? $producto?->costo_referencial ?? 0),
                    'porcentaje_utilidad' => $porcentaje,
                    'precio_unitario' => $precio,
                    'subtotal' => $lineaSubtotal,
                    'descuento' => $descuento,
                    'impuesto' => $impuesto,
                    'total' => max(0, $lineaSubtotal - $descuento) + $impuesto,
                ]);
            }

            return $cotizacion->load('detalles', 'cliente', 'usuario');
        });
    }

    public function convertirAVenta(Cotizacion $cotizacion, int $usuarioId, array $options = []): Venta
    {
        if ($cotizacion->venta_id) {
            throw new InvalidArgumentException('La cotización ya fue convertida en venta.');
        }

        $cotizacion->load('detalles');
        $tipoDocumentoOption = $options['tipo_documento'] ?? 'factura';
        $tipoDocumento = in_array($tipoDocumentoOption, ['factura', 'nota_entrega'], true)
            ? $tipoDocumentoOption
            : 'factura';
        $fecha = $options['fecha'] ?? now()->toDateString();
        $documento = trim((string) ($options['factura'] ?? '')) ?: $cotizacion->numero_cotizacion;

        return DB::transaction(function () use ($cotizacion, $usuarioId, $tipoDocumento, $fecha, $documento) {
            $cotizacion = Cotizacion::whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();
            if ($cotizacion->venta_id) {
                throw new InvalidArgumentException('La cotización ya fue convertida en venta.');
            }
            $cotizacion->load('detalles');
            if ($cotizacion->detalles->contains(fn ($detalle) => $detalle->producto_id === null)) {
                throw new InvalidArgumentException('Vincule todos los artículos por pedido a productos del catálogo antes de convertir la cotización en venta.');
            }
            $venta = $this->sales->registrar([
                'numero_venta' => null,
                'nombre_comercial' => $cotizacion->nombre_comercial,
                'tipo_documento' => $tipoDocumento,
                'cliente_id' => $cotizacion->cliente_id,
                'fecha' => $fecha,
                'factura' => $documento,
                'nit_cliente' => $cotizacion->nit_cliente,
                'lista_precios' => 'Cotizacion',
                'descuento_porcentaje' => $cotizacion->descuento_porcentaje,
                'observacion' => trim('Venta generada desde cotizacion ' . $cotizacion->numero_cotizacion . "\n" . ($cotizacion->observacion ?: '')),
                'items' => $cotizacion->detalles->map(fn ($detalle) => [
                    'producto_id' => $detalle->producto_id,
                    'cantidad' => (float) $detalle->cantidad,
                    'precio_unitario' => (float) $detalle->precio_unitario,
                    'descuento' => (float) $detalle->descuento,
                    'impuesto' => (float) $detalle->impuesto,
                    'costo_envio' => 0,
                    'otros_costos' => 0,
                ])->all(),
            ], $usuarioId);

            $cotizacion->update([
                'estado' => 'convertida',
                'venta_id' => $venta->id,
            ]);

            return $venta;
        });
    }

    private function siguienteNumero(): string
    {
        $next = Cotizacion::query()
            ->where('numero_cotizacion', 'like', 'CT-%')
            ->pluck('numero_cotizacion')
            ->map(fn (string $numero) => preg_match('/^CT-(\d+)$/', $numero, $matches) ? (int) $matches[1] : 0)
            ->max() + 1;

        return 'CT-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function lineAmount(array $item, string $key, float $default = 0): float
    {
        return max(0, (float) ($item[$key] ?? $default));
    }

    private function footerDefaults(?string $commercialName): array
    {
        $slug = str_contains(strtolower((string) $commercialName), 'supra') ? 'supra-medic' : 'san-benjamin';
        $defaults = config('quotation_pdf.footer_defaults');
        $company = config("quotation_pdf.{$slug}.footer_company");

        return [
            'empresa' => $company,
            'direccion' => $defaults['direccion'],
            'validez' => $defaults['validez'],
            'entrega' => $defaults['entrega'],
            'condiciones_pago' => $defaults['condiciones_pago'],
            'deposito_cuenta' => str_replace('{empresa}', $company, $defaults['deposito_cuenta']),
        ];
    }

    private function textOrDefault(?string $value, string $default): string
    {
        $value = trim((string) $value);

        return $value === '' ? $default : $value;
    }
}
