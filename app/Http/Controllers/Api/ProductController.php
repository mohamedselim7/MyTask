<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Actions\Products\ShowProductAction;
use App\Services\StockService;

class ProductController extends Controller
{
  public function show($id)
{
    $cacheKey = "product.{$id}." . now()->format('YmdHi');
    
    return Cache::remember($cacheKey, 5, function () use ($id) {
        $product = Product::select('id', 'name', 'price', 'available_stock')
            ->findOrFail($id);
        $activeHoldsQty = Hold::where('product_id', $id)
            ->where('status', 'reserved')
            ->where('expires_at', '>', now())
            ->whereNull('order_id')
            ->sum('qty');
        
        $actuallyAvailable = max(0, $product->available_stock - $activeHoldsQty);
        
        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'total_stock' => $product->available_stock,
            'available_stock' => $actuallyAvailable,
            'reserved_stock' => $activeHoldsQty,
            'cache_hit' => true
        ];
    });
}
}
