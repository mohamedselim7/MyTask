<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\IdempotencyKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        
        if (!$idempotencyKey) {
            return response()->json(['error' => 'Idempotency-Key header required'], 400);
        }
        $existing = IdempotencyKey::where('key', $idempotencyKey)->first();
        
        if ($existing) {
            Log::info('Duplicate webhook detected', ['key' => $idempotencyKey]);
            return response()->json($existing->response_data);
        }
        
        return DB::transaction(function () use ($request, $idempotencyKey) {
            $order = Order::where('id', $request->input('order_id'))
                ->lockForUpdate()
                ->first();
            
            if (!$order) {
                IdempotencyKey::create([
                    'key' => $idempotencyKey,
                    'request_data' => $request->all(),
                    'response_data' => ['status' => 'pending']
                ]);
                return response()->json(['status' => 'pending']);
            }
            if ($order->payment_status === 'paid') {
                $response = ['status' => 'already_paid'];
            } else {
                if ($request->input('status') === 'success') {
                    $order->update(['payment_status' => 'paid']);
                    $response = ['status' => 'success'];
                } else {
                    $order->hold->product->increment('available_stock', $order->hold->qty);
                    $order->update(['payment_status' => 'failed']);
                    $response = ['status' => 'failed'];
                }
            }
            IdempotencyKey::create([
                'key' => $idempotencyKey,
                'request_data' => $request->all(),
                'response_data' => $response
            ]);
            
            return response()->json($response);
        });
    }
}