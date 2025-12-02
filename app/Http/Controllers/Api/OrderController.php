<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Actions\Orders\CreateOrderAction;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(CreateOrderRequest $request, CreateOrderAction $action): JsonResponse
    {
        try {
            $order = $action->execute($request->validated()['hold_id']);
            
            return response()->json([
                'id' => $order->id,
                'hold_id' => $order->hold_id,
                'product_id' => $order->product_id,
                'qty' => $order->qty,
                'total_price' => $order->total_price,
                'payment_status' => $order->payment_status,
                'created_at' => $order->created_at
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
}