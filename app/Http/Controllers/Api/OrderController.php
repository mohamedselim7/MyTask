<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Actions\Orders\CreateOrderAction;

class OrderController extends Controller
{
    public function create(CreateOrderRequest $request, CreateOrderAction $action)
    {
        $result = $action->execute($request->validated()['hold_id']);

        if ($result['error']) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json($result['data'], $result['status']);
    }
}
