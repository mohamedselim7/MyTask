<?php

namespace App\Actions\Orders;

use App\Models\Hold;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CreateOrderAction
{
    public function execute(string $holdId): Order
    {
        return DB::transaction(function () use ($holdId) {
            $hold = Hold::where('id', $holdId)
                ->where('expires_at', '>', now())
                ->whereNull('order_id')
                ->lockForUpdate()
                ->with('product')
                ->first();
            
            if (!$hold) {
                throw new \Exception('Invalid, expired, or already used hold');
            }
            
            $order = Order::create([
                'hold_id' => $hold->id,
                'product_id' => $hold->product_id,
                'qty' => $hold->qty,
                'total_price' => $hold->product->price * $hold->qty,
                'payment_status' => 'pending',
                'payment_meta' => []
            ]);
            
            $hold->order_id = $order->id;
            $hold->save();
            
            Cache::forget("product.{$hold->product_id}");
            
            Log::info('Order created via Action', [
                'order_id' => $order->id,
                'hold_id' => $hold->id,
                'product_id' => $hold->product_id
            ]);
            
            return $order;
        });
    }
}