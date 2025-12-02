<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Services\StockService;

class ShowProductAction
{
    public function execute(int $productId, StockService $stockService)
    {
        $p = Product::findOrFail($productId);

        return [
            'id' => $p->id,
            'name' => $p->name,
            'price' => $p->price,
            'available' => $stockService->getAvailable($p->id)
        ];
    }
}
