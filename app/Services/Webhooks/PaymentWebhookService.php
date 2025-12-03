<?php

namespace App\Services\Webhooks;

use App\Repositories\IdempotencyRepository;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PaymentWebhookService
{
    private int $maxRetries = 3;

    public function __construct(
        private IdempotencyRepository $idempotency,
        private OrderRepository $orders
    ) {}

    public function process(array $data, string $key)
    {
        if (!$key) {
            return response()->json(['error' => 'Idempotency-Key header required'], 400);
        }

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {

            try {
                return $this->processInsideTransaction($data, $key, $attempt);

            } catch (\Throwable $e) {

                if ($this->isDuplicateError($e)) {

                    Log::warning("Idempotency duplicate race detected", [
                        'attempt' => $attempt,
                        'key'     => $key,
                        'error'   => $e->getMessage(),
                    ]);

                    usleep(rand(50_000, 200_000)); 

                    continue;
                }

                throw $e;
            }
        }

        return response()->json(['status' => 'processing'], 202);
    }
    private function processInsideTransaction(array $data, string $key, int $attempt)
    {
        return DB::transaction(function () use ($data, $key, $attempt) {

            $idempotent = $this->idempotency->updateOrCreate(
                ['key' => $key],
                ['request_data' => $data, 'response_data' => null]
            );

            if ($idempotent->response_data) {
                return response()->json($idempotent->response_data);
            }

            $order = $this->orders->findForUpdate($data['order_id']);

            if (!$order) {

                $response = [
                    'status'  => 'order_not_found',
                    'message' => 'Order not found yet',
                    'retry'   => true,
                ];

                $this->idempotency->saveResponse($idempotent, $response);

                return response()->json($response, 404);
            }

            if ($order->payment_status === 'paid') {

                $response = [
                    'status'  => 'already_paid',
                    'message' => 'Order already paid',
                ];

                $this->idempotency->saveResponse($idempotent, $response);

                return response()->json($response);
            }

            if ($data['status'] === 'success') {

                $order->update([
                    'payment_status' => 'paid',
                    'paid_at'        => now(),
                ]);

                $response = [
                    'status'   => 'success',
                    'order_id' => $order->id,
                    'message'  => 'Payment processed successfully',
                ];

                $this->idempotency->saveResponse($idempotent, $response);

                Cache::forget("product.{$order->product_id}");

                return response()->json($response);
            }

            $order->update([
                'payment_status' => 'failed',
                'failed_at'      => now(),
            ]);

            $this->releaseStock($order);

            $response = [
                'status'   => 'payment_failed',
                'order_id' => $order->id,
                'message'  => 'Payment failed and stock restored',
            ];

            $this->idempotency->saveResponse($idempotent, $response);

            return response()->json($response);
        });
    }


    private function releaseStock($order)
    {
        DB::transaction(function () use ($order) {

            $product = $order->hold->product()->lockForUpdate()->first();
            $product->increment('available_stock', $order->qty);

            Cache::forget("product.{$product->id}");

            Log::info("Stock restored after failed payment", [
                'order_id'     => $order->id,
                'product_id'   => $product->id,
                'qty_restored' => $order->qty
            ]);
        });
    }


    private function isDuplicateError($e)
    {
        return $e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
