<?php

namespace App\Repositories;

use App\Models\Hold;

class HoldRepository
{
    public function find($id)
    {
        return Hold::find($id);
    }

    public function findActiveHold($id)
    {
        return Hold::where('id', $id)
            ->where('expires_at', '>', now())
            ->whereNull('order_id')
            ->first();
    }

    public function create(array $data)
    {
        return Hold::create($data);
    }

    public function update($id, array $data)
    {
        $hold = Hold::findOrFail($id);
        $hold->update($data);
        return $hold;
    }

    public function getActiveHoldsForProduct($productId)
    {
        return Hold::where('product_id', $productId)
            ->where('status', 'reserved')
            ->where('expires_at', '>', now())
            ->whereNull('order_id')
            ->get();
    }

    public function sumActiveHoldsQty($productId)
    {
        return Hold::where('product_id', $productId)
            ->where('status', 'reserved')
            ->where('expires_at', '>', now())
            ->whereNull('order_id')
            ->sum('qty');
    }
}