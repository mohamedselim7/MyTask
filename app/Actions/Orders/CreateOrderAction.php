<?php

namespace App\Actions\Orders;

use App\Models\Hold;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class CreateOrderAction
{
    public function execute(int $holdId)
    {
        return DB::transaction(function() use ($holdId) {

            $hold = Hold::where('id', $holdId)
                ->lockForUpdate()
                ->first();

            if (!$hold || $hold->status !== 'active' || !$hold->expires_at || $hold->expires_at->isPast()) {
                return [
                    'error' => true,
                    'status' => 409,
                    'message' => 'invalid_or_expired_hold'
                ];
            }

            $hold->update(['status' => 'used']);

            $order = Order::create([
                'hold_id' => $hold->id,
                'status' => 'pending',
            ]);

            return [
                'error' => false,
                'status' => 201,
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status
                ]
            ];
        }, 5);
    }
}
