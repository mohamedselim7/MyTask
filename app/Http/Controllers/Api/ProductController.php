<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Actions\Products\ShowProductAction;
use App\Services\StockService;

class ProductController extends Controller
{
   public function show($id)
{
    $cacheKey = "product.{$id}";
    
    $productData = Cache::remember($cacheKey, 30, function () use ($id) {
        $product = Product::select('id', 'name', 'price', 'available_stock')
            ->findOrFail($id);
        $activeHoldsQty = Hold::where('product_id', $id)
            ->where('status', 'reserved')
            ->where('expires_at', '>', now())
            ->sum('qty');
        
        return [
            'product' => $product,
            'active_holds_qty' => $activeHoldsQty
        ];
    });
    $actuallyAvailable = max(0, 
        $productData['product']->available_stock - $productData['active_holds_qty']
    );
    return response()->json([
        'id' => $productData['product']->id,
        'name' => $productData['product']->name,
        'price' => $productData['product']->price,
        'total_stock' => $productData['product']->available_stock,
        'available_stock' => $actuallyAvailable,
        'reserved_stock' => $productData['active_holds_qty']
    ]);
}
}
