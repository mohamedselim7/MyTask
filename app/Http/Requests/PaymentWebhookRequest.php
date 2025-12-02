<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentWebhookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer'],
            'status' => ['required', 'in:success,failed'],
            'payment_id' => ['nullable', 'string']
        ];
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->header('Idempotency-Key');
    }
}
