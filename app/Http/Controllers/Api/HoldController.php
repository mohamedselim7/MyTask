<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateHoldRequest;
use App\Actions\CreateHoldAction;

class HoldController extends Controller
{
   public function store(CreateHoldRequest $request, CreateHoldAction $action)
{
    $hold = $action->execute($request->validated());

    return response()->json([
        'status' => true,
        'hold_id' => $hold->id,
        'expires_at' => $hold->expires_at,
    ]);
}
}
