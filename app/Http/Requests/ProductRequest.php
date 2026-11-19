<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'moneda_costo' => $this->input('moneda_costo', 'usd'),
        ]);
    }

    public function authorize(): bool
    {
        // Editing and creating products is controlled by the module
        // permission. Google Authenticator is reserved for deletion actions.
        return $this->user()?->hasPermission('productos') ?? false;
    }

    public function rules(): array
    {
        $producto = $this->route('producto');

        return [
            'codigo' => [$producto ? 'required' : 'nullable', 'string', 'max:255', Rule::unique('productos', 'codigo')->ignore($producto)],
            'nombre' => ['required', 'string', 'max:255'],
            'marca' => ['nullable', 'string', 'max:255'],
            'procedencia' => ['required', 'string', 'max:255'],
            'categoria_id' => ['nullable', 'exists:categorias,id'],
            'descripcion' => ['nullable', 'string'],
            'medida' => ['nullable', 'string', 'max:100'],
            'ubicacion' => ['nullable', 'string', 'max:100'],
            'moneda_costo' => ['required', 'in:usd,bs'],
            'precio_ingreso_usd' => ['nullable', 'numeric', 'min:0'],
            'costo_ingreso_bs' => ['nullable', 'numeric', 'min:0'],
            'tipo_cambio' => ['required', 'numeric', 'gt:0'],
            'porcentaje_utilidad' => ['required', 'numeric', 'min:25', 'max:30'],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
            'stock_inicial' => ['nullable', 'numeric', 'min:0'],
            'codigo_lote' => ['nullable', 'string', 'max:255'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'activo' => ['nullable', 'boolean'],
            'maneja_lote_universal' => ['nullable', 'boolean'],
        ];
    }

}
