<?php 
namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class StockService {
     public function getAvailable(int $productId): int {
        $cacheKey = "product:{$productId}:available";
        if ($value = Cache::get($cacheKey)) {
            return (int)$value;
        }
        $product = Product::findOrFail($productId);
        $available = max(0, $product->stock - $product->reserved);
        Cache::put($cacheKey, $available, 30); 
        return $available;
    }

    
public function createHold(int $productId, int $qty, int $holdSeconds = 120) {
        return DB::transaction(function() use ($productId,$qty,$holdSeconds) {
            $product = Product::where('id',$productId)->lockForUpdate()->firstOrFail();
            $available = $product->stock - $product->reserved;
            if ($qty <= 0 || $qty > $available) {
                throw new \Exception('insufficient_stock');
            }
            $product->reserved += $qty;
            $product->save();

            $expiresAt = Carbon::now()->addSeconds($holdSeconds);
            $hold = \App\Models\Hold::create([
                'product_id' => $productId,
                'qty' => $qty,
                'expires_at' => $expiresAt,
                'status' => 'active'
            ]);

            Cache::put("product:{$productId}:available", max(0, $product->stock - $product->reserved), now()->addSeconds(30));

-            \App\Jobs\ExpireHoldJob::dispatch($hold)->delay(now()->diffInSeconds($expiresAt));
+            \App\Jobs\ExpireHoldJob::dispatch($hold)->delay(now()->diffInSeconds($expiresAt))->afterCommit();

            return $hold;
        }, 5); 
    }

    public function releaseHold(\App\Models\Hold $hold) {
        return DB::transaction(function() use ($hold) {
-            $hold = $hold->lockForUpdate();
+            $hold = \App\Models\Hold::where('id', $hold->id)->lockForUpdate()->firstOrFail();
             if ($hold->status !== 'active') return false;
-            $product = Product::where('id', $hold->product_id)->lockForUpdate()->first();
+            $product = Product::where('id', $hold->product_id)->lockForUpdate()->firstOrFail();
             $product->reserved = max(0, $product->reserved - $hold->qty);
             $product->save();
             $hold->status = 'expired';
             $hold->save();
             Cache::put("product:{$product->id}:available", max(0, $product->stock - $product->reserved), now()->addSeconds(30));
             return true;
        });
    }
}
