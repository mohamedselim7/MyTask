<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Actions\Products\ShowProductAction;
use App\Services\StockService;
use App\Http\Requests\StoreProductRequest;
use App\Actions\Products\CreateProductAction;
use Illuminate\Support\Facades\Cache;
use App\Models\Product;
use App\Models\Hold;
class ProductController extends Controller
{

 public function show($id)
{
    $cacheKey = "product.{$id}";
    
    $startTime = microtime(true);
    $cacheHit = Cache::has($cacheKey);

    $data = Cache::remember($cacheKey, 30, function () use ($id) {
        
        $product = Product::select('id', 'name', 'price', 'stock', 'reserved')
            ->findOrFail($id);

        $activeHoldsQty = Hold::where('product_id', $id)
            ->where('status', 'reserved')
            ->where('expires_at', '>', now())
            ->whereNull('order_id')
            ->sum('qty');

        $availableStock = max(0, $product->stock - $product->reserved - $activeHoldsQty);

        return [
            'product' => $product,
            'active_holds_qty' => $activeHoldsQty,
            'available_stock' => $availableStock,
            'calculated_at' => now()
        ];
    });
    
    $responseTime = (microtime(true) - $startTime) * 1000;
    \Log::channel('metrics')->info('Product API metrics', [
        'product_id' => $id,
        'response_time_ms' => $responseTime,
        'cache_hit' => $cacheHit, 
        'active_holds' => $data['active_holds_qty']
    ]);
    
    return response()->json([
        'id' => $data['product']->id,
        'name' => $data['product']->name,
        'price' => (float) $data['product']->price,
        'total_stock' => $data['product']->stock,
        'available_stock' => $data['available_stock'], 
        'reserved_stock' => $data['product']->reserved + $data['active_holds_qty'],
        'last_updated' => $data['calculated_at']->toISOString()
    ]);
}

 public function store(StoreProductRequest $request, CreateProductAction $action)
    {
        $product = $action->execute($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Product created successfully',
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'stock' => $product->stock,
                'reserved' => $product->reserved,
                'available' => $product->stock - $product->reserved,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ]
        ], 201);
    }
}

