<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\Hold;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CreateOrderAction
{
    public function execute(int $holdId): Order
    {
        return DB::transaction(function () use ($holdId) {

            $hold = Hold::where('id', $holdId)
                ->where('expires_at', '>', now())
                ->whereNull('order_id')
                ->lockForUpdate()
                ->first();

            if (!$hold) {
                throw new \Exception('Invalid, expired, or already used hold');
            }

            $product = Product::where('id', $hold->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            $order = Order::create([
                'hold_id' => $hold->id,
                'product_id' => $hold->product_id,
                'qty' => $hold->qty,
                'total_price' => $product->price * $hold->qty,
                'payment_status' => 'pending',
            ]);

            $hold->order_id = $order->id;
            $hold->save();
            Cache::forget("product.{$hold->product_id}");

            return $order;
        });
    }
}
