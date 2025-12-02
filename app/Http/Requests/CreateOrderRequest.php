<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hold_id' => 'required|exists:holds,id'
        ];
    }

    public function messages(): array
    {
        return [
            'hold_id.required' => 'Hold ID is required',
            'hold_id.exists' => 'The selected hold does not exist'
        ];
    }
}