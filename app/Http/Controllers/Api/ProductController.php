<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Actions\Products\ShowProductAction;
use App\Services\StockService;

class ProductController extends Controller
{
    public function show($id, StockService $stockService, ShowProductAction $action)
    {
        $data = $action->execute($id, $stockService);
        return response()->json($data);
    }
}
