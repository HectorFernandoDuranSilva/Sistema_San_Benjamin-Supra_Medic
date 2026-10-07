<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'tipo_documento' => ['nullable', 'in:factura,nota_entrega'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'fecha' => ['required', 'date'],
            'factura' => ['nullable', 'string', 'max:255'],
            'nit_proveedor' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.costo_unitario' => ['required', 'numeric', 'gt:0'],
            'items.*.porcentaje_utilidad' => ['required', 'numeric', 'min:25', 'max:30'],
            'items.*.descuento_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.codigo_lote' => ['nullable', 'string', 'max:255'],
            'items.*.fecha_vencimiento' => ['nullable', 'date'],
            'items.*.impuesto' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
