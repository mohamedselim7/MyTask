<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateHoldRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
        ];
    }
}
