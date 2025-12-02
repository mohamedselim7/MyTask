<?php
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\HoldController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentWebhookController;


Route::get('products/{id}', [ProductController::class, 'show']);
Route::post('holds', [HoldController::class,'create']);
Route::post('orders', [OrderController::class,'store']);
Route::post('payments/webhook', [PaymentWebhookController::class,'handle']);