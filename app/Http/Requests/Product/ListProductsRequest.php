<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProductsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'per_page'   => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page'       => ['sometimes', 'integer', 'min:1'],
            'sort_price' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'search'     => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'sort_price.in' => 'El parámetro sort_price debe ser "asc" o "desc".',
            'search.max'    => 'La búsqueda no puede superar los 100 caracteres.',
        ];
    }
}
