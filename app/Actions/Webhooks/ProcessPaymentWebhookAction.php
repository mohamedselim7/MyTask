<?php

namespace App\Actions\Webhooks;

use App\Http\Requests\PaymentWebhookRequest;
use App\Services\Webhooks\PaymentWebhookService;

class ProcessPaymentWebhookAction
{
    public function __construct(private PaymentWebhookService $service) {}

    public function execute(PaymentWebhookRequest $request)
    {
        return $this->service->process(
            $request->validated(),
            $request->getIdempotencyKey()
        );
    }
}
