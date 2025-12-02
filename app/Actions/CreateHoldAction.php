<?php

namespace App\Actions;

use App\Services\HoldService;

class CreateHoldAction
{
    public function __construct(private HoldService $service) {}

    public function execute(array $data)
    {
        return $this->service->createHold(
            productId: $data['product_id'],
            qty: $data['qty']
        );
    }
}
