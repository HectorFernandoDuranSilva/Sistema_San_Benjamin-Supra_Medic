<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->activo;
    }

    public function rules(): array
    {
        return [
            'numero_compra' => ['nullable', 'string', 'max:255', 'unique:compras,numero_compra'],
            'nombre_comercial' => ['required', 'in:San Benjamin,Supra Medic'],
            'tipo_documento' => ['nullable', 'in:factura,nota_entrega'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'fecha' => ['required', 'date'],
            'factura' => ['nullable', 'string', 'max:255'],
            'nit_proveedor' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string'],
            'plan_pago' => ['nullable', 'in:contado,cuotas'],
            'cuotas' => ['nullable', 'array', 'min:2', 'max:120'],
            'cuotas.*.fecha_vencimiento' => ['required_with:cuotas', 'date', 'after_or_equal:fecha'],
            'cuotas.*.importe' => ['required_with:cuotas', 'numeric', 'decimal:0,2', 'gt:0'],
            'cuotas.*.observacion' => ['nullable', 'string', 'max:255'],
            'items' => ['nullable', 'array'],
            'items.*.producto_id' => ['required', Rule::exists('productos', 'id')->where(fn ($query) => $query->where('activo', true))],
            'items.*.cantidad' => ['required', 'numeric', 'min:0'],
            // A purchase may be received free of charge (gift/bonus).
            'items.*.costo_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.porcentaje_utilidad' => ['required', 'numeric', 'min:25', 'max:30'],
            'items.*.descuento_fijo' => ['nullable', 'numeric', 'min:0'],
            'items.*.codigo_lote' => ['nullable', 'string', 'max:255'],
            'items.*.fecha_vencimiento' => ['nullable', 'date'],
            'items.*.tipo_lote' => ['nullable', 'in:con_lote,sin_vencimiento,sin_lote'],
            'items.*.impuesto' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->map(function ($item) {
                $tipoLote = $item['tipo_lote'] ?? 'con_lote';
                if ($tipoLote === 'sin_lote') {
                    $item['codigo_lote'] = null;
                    $item['fecha_vencimiento'] = null;
                } elseif ($tipoLote === 'sin_vencimiento') {
                    $item['fecha_vencimiento'] = null;
                }
                $fechaVencimiento = trim((string) ($item['fecha_vencimiento'] ?? ''));

                if ($fechaVencimiento !== '' && preg_match('/^\d{1,2}[\/-]\d{1,2}[\/-]\d{4}$/', $fechaVencimiento)) {
                    $fechaIngresada = str_replace('-', '/', $fechaVencimiento);

                    try {
                        $fecha = CarbonImmutable::createFromFormat('!d/m/Y', $fechaIngresada);

                        // Evita aceptar fechas inexistentes, como 31/02/2029.
                        if ($fecha !== false && $fecha->format('d/m/Y') === $fechaIngresada) {
                            $item['fecha_vencimiento'] = $fecha->format('Y-m-d');
                        }
                    } catch (\Throwable) {
                        // La regla de validacion mostrara el error correspondiente.
                    }
                }

                return $item;
            })
            ->all();

        $this->merge(['items' => $items]);
    }

}
