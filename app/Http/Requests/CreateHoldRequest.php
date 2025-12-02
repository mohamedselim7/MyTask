<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1|max:10'
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Product ID is required',
            'product_id.exists' => 'The selected product does not exist',
            'qty.required' => 'Quantity is required',
            'qty.min' => 'Quantity must be at least 1',
            'qty.max' => 'Quantity cannot exceed 10'
        ];
    }
}