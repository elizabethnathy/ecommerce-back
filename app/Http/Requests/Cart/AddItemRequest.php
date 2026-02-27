<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity'   => ['sometimes', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'El ID del producto es obligatorio.',
            'product_id.integer'  => 'El ID del producto debe ser un número entero.',
            'quantity.min'        => 'La cantidad debe ser al menos 1.',
            'quantity.max'        => 'La cantidad no puede superar 999.',
        ];
    }
}
