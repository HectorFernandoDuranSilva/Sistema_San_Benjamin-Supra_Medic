<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->activo;
    }

    public function rules(): array
    {
        return [
            'numero_cotizacion' => ['nullable', 'string', 'max:255', Rule::unique('cotizaciones', 'numero_cotizacion')->ignore($this->route('cotizacion'))],
            'revision' => [$this->route('cotizacion') ? 'required' : 'nullable', 'integer', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'fecha' => ['required', 'date'],
            'valida_hasta' => ['nullable', 'date', 'after_or_equal:fecha'],
            'nit_cliente' => ['nullable', 'string', 'max:255'],
            'descuento_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'observacion' => ['nullable', 'string'],
            'footer_direccion' => ['nullable', 'string', 'max:255'],
            'footer_validez' => ['nullable', 'string', 'max:255'],
            'footer_entrega' => ['nullable', 'string', 'max:1000'],
            'footer_condiciones_pago' => ['nullable', 'string', 'max:255'],
            'footer_deposito_cuenta' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['nullable', 'exists:productos,id'],
            'items.*.por_pedido' => ['nullable', 'boolean'],
            'items.*.nombre_producto' => ['nullable', 'string', 'max:255'],
            'items.*.medida' => ['nullable', 'string', 'max:100'],
            'items.*.marca' => ['nullable', 'string', 'max:255'],
            'items.*.procedencia' => ['nullable', 'string', 'max:255'],
            'items.*.fecha_vencimiento' => ['nullable', 'date_format:Y-m-d'],
            'items.*.inventario_lote_id' => ['nullable', 'integer', 'exists:inventario_lotes,id'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.porcentaje_utilidad' => ['required', 'numeric', 'min:25', 'max:30'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'items.*.impuesto' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
