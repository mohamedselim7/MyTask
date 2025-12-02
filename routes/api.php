<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\HoldController;
use App\Http\Controllers\Api\OrderController; 
use App\Http\Controllers\Api\PaymentWebhookController;

Route::prefix('api')->group(function () {
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::post('/products', [ProductController::class, 'store']); 
    
    // Hold routes
    Route::post('/holds', [HoldController::class, 'store']);
    
    // Order routes (Note: OrderController is in Api namespace)
    Route::post('/orders', [OrderController::class, 'store']);
    
    // Payment webhook
    Route::post('/payments/webhook', [PaymentWebhookController::class, 'handle']);
});