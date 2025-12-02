<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Hold;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller {
    public function handle(Request $request) {
        $key = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');
        if (!$key) return response()->json(['message'=>'missing_idempotency_key'], 400);

        $action = 'payments:webhook';
        return DB::transaction(function() use ($request,$key,$action) {
            $existing = IdempotencyKey::where('key',$key)->where('action',$action)->first();
            if ($existing) {
                Log::info('webhook.duplicate', ['key'=>$key]);
                return response()->json($existing->response ?? ['message'=>'duplicate'], 200);
            }

            $ipr = IdempotencyKey::create(['key'=>$key,'action'=>$action,'request'=>$request->all()]);
            $orderId = $request->input('order_id');
            $status = $request->input('status'); 
            $paymentMeta = $request->input('payment_meta', []);

            $order = Order::lockForUpdate()->find($orderId);

            if (!$order) {
                $response = ['message'=>'order_not_found_yet'];
                $ipr->update(['response'=>$response]);
                Log::info('webhook.order_missing', ['key'=>$key,'order_id'=>$orderId]);
                return response()->json($response, 202);
            }

            if ($status === 'paid') {
                if ($order->status === 'paid') {
                    $resp = ['message'=>'already_paid', 'order_id'=>$order->id];
                } else {
                    $order->status = 'paid';
                    $order->payment_meta = $paymentMeta;
                    $order->save();
                    $resp = ['message'=>'ok','order_id'=>$order->id];
                }
            } else {
                if ($order->status === 'paid') {
                    $resp = ['message'=>'cannot_cancel_paid'];
                } else {
                    $order->status = 'cancelled';
                    $order->payment_meta = $paymentMeta;
                    $order->save();
                    $hold = $order->hold()->lockForUpdate()->first();
                    if ($hold && $hold->status === 'used') {
                        $hold->status = 'cancelled';
                        $hold->save();
                        $product = $hold->product()->lockForUpdate()->first();
                        $product->reserved = max(0, $product->reserved - $hold->qty);
                        $product->save();
                        \Illuminate\Support\Facades\Cache::put("product:{$product->id}:available", max(0,$product->stock-$product->reserved), 30);
                    }
                    $resp = ['message'=>'cancelled','order_id'=>$order->id];
                }
            }

            $ipr->update(['response'=>$resp]);
            Log::info('webhook.processed', ['key'=>$key,'order_id'=>$order->id,'resp'=>$resp]);
            return response()->json($resp, 200);
        }, 5);
    }
}
