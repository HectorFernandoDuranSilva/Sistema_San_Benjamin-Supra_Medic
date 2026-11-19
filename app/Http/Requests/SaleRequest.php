<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Producto;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->activo;
    }

    public function rules(): array
    {
        return [
            'numero_venta' => ['nullable', 'string', 'max:255', 'unique:ventas,numero_venta'],
            'nombre_comercial' => ['required', 'in:San Benjamin,Supra Medic'],
            'tipo_documento' => ['nullable', 'in:factura,nota_entrega'],
            'modo_stock' => ['nullable', 'in:normal,pendiente'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'fecha' => ['required', 'date'],
            'factura' => ['nullable', 'string', 'max:255'],
            'nit_cliente' => ['nullable', 'string', 'max:255'],
            'lista_precios' => ['nullable', 'string', 'max:255'],
            'descuento_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string'],
            'tarjeta_magnetica' => ['nullable', 'boolean'],
            'tipo_tarjeta' => ['nullable', 'required_if:tarjeta_magnetica,1', 'in:genrui,getein'],
            'destinatario_tarjeta' => ['nullable', 'string', 'max:255'],
            'permitir_salida_sin_stock' => ['nullable', 'boolean'],
            'factura_stock_pendiente' => ['nullable', 'boolean'],
            'motivo_salida_sin_stock' => ['nullable', 'string', 'max:1000', 'required_if:permitir_salida_sin_stock,1'],
            'plan_pago' => ['nullable', 'in:contado,cuotas'],
            'cuotas' => ['nullable', 'array', 'min:2', 'max:120'],
            'cuotas.*.fecha_vencimiento' => ['required_with:cuotas', 'date', 'after_or_equal:fecha'],
            'cuotas.*.importe' => ['required_with:cuotas', 'numeric', 'decimal:0,2', 'gt:0'],
            'cuotas.*.observacion' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', Rule::exists('productos', 'id')->where(fn ($query) => $query->where('activo', true))],
            'items.*.tarjeta_magnetica' => ['nullable', 'boolean'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.precio_unitario' => [
                'required',
                'numeric',
                'min:0',
            ],
            'items.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'items.*.impuesto' => ['nullable', 'numeric', 'min:0'],
            'items.*.costo_envio' => ['nullable', 'numeric', 'min:0'],
            'items.*.otros_costos' => ['nullable', 'numeric', 'min:0'],
            'items.*.inventario_lote_id' => ['nullable', 'exists:inventario_lotes,id'],
            'items.*.fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:today'],
            'items.*.codigo_lote_manual' => ['nullable', 'string', 'max:255'],
            'items.*.fecha_vencimiento_manual' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $items = collect($this->input('items', []));
            $lineaConTarjeta = $items->contains(fn ($item) => (bool) ($item['tarjeta_magnetica'] ?? false));

            if ($lineaConTarjeta && ! in_array($this->input('tipo_tarjeta'), ['genrui', 'getein'], true)) {
                $validator->errors()->add('tipo_tarjeta', 'Seleccione Genrui o Getein cuando un producto lleve C/T.');
            }

            $productos = Producto::whereIn('id', $items->pluck('producto_id')->filter())->get()->keyBy('id');
            foreach ($items as $index => $item) {
                $cantidad = (float) ($item['cantidad'] ?? 0);
                $precio = (float) ($item['precio_unitario'] ?? 0);
                $subtotal = round($cantidad * $precio, 2);
                $descuento = array_key_exists('descuento', $item)
                    ? (float) $item['descuento']
                    : round($subtotal * ((float) ($this->input('descuento_porcentaje') ?? 0) / 100), 2);
                if ($descuento > $subtotal + 0.0001) {
                    $validator->errors()->add("items.{$index}.descuento", 'El descuento no puede superar el subtotal del producto.');
                }

                if (! (bool) ($item['tarjeta_magnetica'] ?? false)) {
                    continue;
                }

                $producto = $productos->get((int) ($item['producto_id'] ?? 0));
                $identificacion = strtolower(trim(implode(' ', array_filter([
                    (string) ($producto?->marca ?? ''),
                    (string) ($producto?->nombre ?? ''),
                ]))));
                $marcaTarjetaPermitida = str_contains($identificacion, 'genrui')
                    || str_contains($identificacion, 'getein');
                if (! $marcaTarjetaPermitida) {
                    $validator->errors()->add("items.{$index}.tarjeta_magnetica", 'C/T solo aplica a productos de marca Genrui o Getein.');
                }
            }
        });
    }
}
