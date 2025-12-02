<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentWebhookRequest;
use App\Actions\Webhooks\ProcessPaymentWebhookAction;

class PaymentWebhookController extends Controller
{
    public function handle(PaymentWebhookRequest $request, ProcessPaymentWebhookAction $action)
    {
        return $action->execute($request);
    }
}
