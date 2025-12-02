<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\IdempotencyKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        
        if (!$idempotencyKey) {
            return response()->json(['error' => 'Idempotency-Key header required'], 400);
        }
        
        // محاولة إنشاء أو الحصول على idempotency record
        try {
            $idempotencyRecord = DB::transaction(function () use ($idempotencyKey, $request) {
                return IdempotencyKey::firstOrCreate(
                    ['key' => $idempotencyKey],
                    [
                        'request_data' => $request->all(),
                        'response_data' => null, // null يعني لم يتم المعالجة بعد
                        'status' => 'processing'
                    ]
                );
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // تم معالجة الطلب بشكل متزامن
            usleep(50000); // انتظر 50ms
            $idempotencyRecord = IdempotencyKey::where('key', $idempotencyKey)->first();
            
            if ($idempotencyRecord->response_data) {
                return response()->json($idempotencyRecord->response_data);
            }
            
            // انتظر حتى يكتمل المعالجة
            $attempts = 0;
            while ($attempts < 10 && !$idempotencyRecord->response_data) {
                usleep(100000); // 100ms
                $idempotencyRecord->refresh();
                $attempts++;
            }
            
            if ($idempotencyRecord->response_data) {
                return response()->json($idempotencyRecord->response_data);
            }
            
            return response()->json(['status' => 'processing'], 202);
        }
        
        // إذا كان الطلب قد تمت معالجته مسبقاً
        if ($idempotencyRecord->response_data) {
            return response()->json($idempotencyRecord->response_data);
        }
        
        // معالجة الطلب
        return DB::transaction(function () use ($request, $idempotencyRecord) {
            $order = Order::where('id', $request->input('order_id'))
                ->lockForUpdate()
                ->with(['hold.product'])
                ->first();
            
            $response = null;
            
            if (!$order) {
                // out-of-order webhook: حفظ للمستقبل
                $response = ['status' => 'order_not_found', 'retry' => true];
            } else {
                // تأكد من عدم تكرار الدفع
                if ($order->payment_status === 'paid') {
                    $response = ['status' => 'already_paid'];
                } else {
                    if ($request->input('status') === 'success') {
                        $order->payment_status = 'paid';
                        $order->save();
                        $response = ['status' => 'success'];
                    } else {
                        // فشل الدفع: إرجاع الـ stock
                        $product = $order->hold->product;
                        $product->increment('available_stock', $order->qty);
                        $order->payment_status = 'failed';
                        $order->save();
                        
                        // تنظيف الـ cache
                        Cache::forget("product.{$product->id}");
                        
                        $response = ['status' => 'payment_failed'];
                    }
                }
            }
            
            // حفظ الـ response
            $idempotencyRecord->update([
                'response_data' => $response,
                'status' => 'completed'
            ]);
            
            return response()->json($response);
        });
    }
}