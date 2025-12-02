<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\Hold;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CreateHoldAction
{
    public function execute(array $data): Hold
    {
        return DB::transaction(function () use ($data) {
            $product = Product::where('id', $data['product_id'])
                ->lockForUpdate()
                ->firstOrFail();
            
            $activeHoldsQty = Hold::where('product_id', $product->id)
                ->where('status', 'reserved')
                ->where('expires_at', '>', now())
                ->whereNull('order_id')
                ->sum('qty');
            
            $actuallyAvailable = $product->stock - $product->reserved - $activeHoldsQty;
            
            if ($actuallyAvailable < $data['qty']) {
                throw new \Exception('Insufficient stock available');
            }
            
            $product->increment('reserved', $data['qty']);
            
            $hold = Hold::create([
                'product_id' => $product->id,
                'qty' => $data['qty'],
                'expires_at' => now()->addMinutes(2),
                'status' => 'reserved'
            ]);
            
            Cache::forget("product.{$product->id}");
            
            Log::info('Hold created via Action', [
                'hold_id' => $hold->id,
                'product_id' => $product->id,
                'qty' => $data['qty']
            ]);
            
            return $hold;
        });
    }
}