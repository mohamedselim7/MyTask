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
            return response()->json($order, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
