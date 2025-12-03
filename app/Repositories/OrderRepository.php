<?php

namespace App\Repositories;

use App\Models\Order;

class OrderRepository
{
    public function findForUpdate($orderId)
    {
        return Order::where('id', $orderId)
            ->lockForUpdate()
            ->first();
    }
    
    public function find($orderId)
    {
        return Order::find($orderId);
    }
    
    public function create(array $data)
    {
        return Order::create($data);
    }
    
    public function update($orderId, array $data)
    {
        $order = Order::findOrFail($orderId);
        $order->update($data);
        return $order;
    }
    
    public function delete($orderId)
    {
        return Order::destroy($orderId);
    }
}