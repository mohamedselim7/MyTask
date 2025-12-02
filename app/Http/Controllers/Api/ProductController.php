<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Actions\Products\ShowProductAction;
use App\Services\StockService;

class ProductController extends Controller
{
    public function show($id)
{
    $product = Cache::remember("product.{$id}", 5, function () use ($id) {
        return Product::select('id', 'name', 'price', 'available_stock')
            ->withCount(['holds' => function ($query) {
                $query->where('status', 'reserved')
                      ->where('expires_at', '>', now());
            }])
            ->findOrFail($id);
    });
    $availableStock = $product->available_stock - $product->holds_count;
    return response()->json([
        'id' => $product->id,
        'name' => $product->name,
        'price' => $product->price,
        'available_stock' => max(0, $availableStock)
    ]);
}
}
