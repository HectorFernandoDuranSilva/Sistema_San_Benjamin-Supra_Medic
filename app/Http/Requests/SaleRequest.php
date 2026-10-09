<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'tipo_documento' => ['nullable', 'in:factura,nota_entrega'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'fecha' => ['required', 'date'],
            'factura' => ['nullable', 'string', 'max:255'],
            'nit_cliente' => ['nullable', 'string', 'max:255'],
            'lista_precios' => ['nullable', 'string', 'max:255'],
            'descuento_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string'],
            'permitir_salida_sin_stock' => ['nullable', 'boolean'],
            'motivo_salida_sin_stock' => ['nullable', 'string', 'max:1000', 'required_if:permitir_salida_sin_stock,1'],
            'plan_pago' => ['nullable', 'in:contado,cuotas'],
            'cuotas' => ['nullable', 'array', 'min:2', 'max:120'],
            'cuotas.*.fecha_vencimiento' => ['required_with:cuotas', 'date', 'after_or_equal:fecha'],
            'cuotas.*.importe' => ['required_with:cuotas', 'numeric', 'decimal:0,2', 'gt:0'],
            'cuotas.*.observacion' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.precio_unitario' => [
                'required',
                'numeric',
                Rule::when($this->input('tipo_documento') === 'nota_entrega', ['min:0'], ['gt:0']),
            ],
            'items.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'items.*.impuesto' => ['nullable', 'numeric', 'min:0'],
            'items.*.costo_envio' => ['nullable', 'numeric', 'min:0'],
            'items.*.otros_costos' => ['nullable', 'numeric', 'min:0'],
            'items.*.inventario_lote_id' => ['nullable', 'exists:inventario_lotes,id'],
            'items.*.fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
