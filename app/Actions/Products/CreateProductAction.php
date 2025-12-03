<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateProductAction
{
    public function execute(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create(array_merge($data, ['reserved' => 0]));
            Cache::forget("product.{$product->id}");
            Log::info('Product created', [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'stock' => $product->stock,
            ]);

            return $product;
        });
    }
}
