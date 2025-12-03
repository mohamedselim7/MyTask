<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\CreateHoldRequest;
use App\Actions\CreateHoldAction;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class HoldController extends Controller
{
    public function store(CreateHoldRequest $request, CreateHoldAction $action): JsonResponse
    {
        try {
            $hold = $action->execute($request->validated());
            
            return response()->json([
                'status' => true,
                'hold_id' => $hold->id,
                'expires_at' => $hold->expires_at,
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}