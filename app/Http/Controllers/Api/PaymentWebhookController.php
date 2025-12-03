<?php

namespace App\Http\Controllers\Api;
use App\Models\IdempotencyKey;
use App\Http\Requests\PaymentWebhookRequest;
use App\Actions\Webhooks\ProcessPaymentWebhookAction;
use App\Repositories\OrderRepository;
use App\Repositories\IdempotencyRepository;
use App\Repositories\HoldRepository;
use App\Http\Controllers\Controller;


class PaymentWebhookController extends Controller
{
    public function handle(PaymentWebhookRequest $request, ProcessPaymentWebhookAction $action)
    {
        return $action->execute($request);
    }
}
