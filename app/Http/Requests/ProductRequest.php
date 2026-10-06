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
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $producto = $this->route('producto');

        return [
            'codigo' => ['required', 'string', 'max:255', Rule::unique('productos', 'codigo')->ignore($producto)],
            'nombre' => ['required', 'string', 'max:255'],
            'marca' => ['required', 'string', 'max:255'],
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
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $stockInicial = (float) $this->input('stock_inicial', 0);
                $tieneLote = filled($this->input('codigo_lote')) || filled($this->input('fecha_vencimiento'));

                if ($tieneLote && $stockInicial <= 0) {
                    $validator->errors()->add('stock_inicial', 'Para registrar lote o vencimiento debe indicar stock inicial mayor a cero.');
                }
            },
        ];
    }
}
