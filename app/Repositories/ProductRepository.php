<?php

namespace App\Repositories;

use App\Models\Product;

class ProductRepository
{
    public function find($id)
    {
        return Product::find($id);
    }

    public function findWithLock($id)
    {
        return Product::where('id', $id)->lockForUpdate()->first();
    }

    public function create(array $data)
    {
        return Product::create($data);
    }

    public function updateStock($id, $quantity)
    {
        $product = Product::findOrFail($id);
        $product->increment('reserved', $quantity);
        return $product;
    }

    public function releaseStock($id, $quantity)
    {
        $product = Product::findOrFail($id);
        $product->decrement('reserved', $quantity);
        return $product;
    }
}