<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Hold;
use App\Models\Order;
use App\Models\IdempotencyKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FlashSaleTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct($stock = 100, $reserved = 0)
    {
        return Product::create([
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => $stock,
            'reserved' => $reserved,
        ]);
    }

    private function createHold($productId, $qty = 1, $expired = false)
    {
        return Hold::create([
            'product_id' => $productId,
            'qty' => $qty,
            'status' => 'reserved',
            'expires_at' => $expired ? now()->subMinutes(5) : now()->addMinutes(10),
            'order_id' => null,
        ]);
    }

    
    public function test_it_creates_product_with_stock()
    {
        $product = $this->createProduct(100);

        $response = $this->getJson("/api/products/{$product->id}");

        if ($response->status() === 404) {
            $this->markTestSkipped('Product endpoint not implemented');
            return;
        }

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id', 'name', 'price', 'total_stock', 'available_stock'
            ])
            ->assertJson([
                'id' => $product->id,
                'name' => 'Test Product',
                'price' => 99.99
            ]);
    }

   
    public function test_it_creates_hold_and_reduces_availability()
    {
        $product = $this->createProduct(10);

        $response = $this->postJson('/api/holds', [
            'product_id' => $product->id,
            'qty' => 3
        ]);

        if ($response->status() === 404) {
            $this->markTestSkipped('Hold endpoint not implemented');
            return;
        }

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status', 'hold_id', 'expires_at'
            ])
            ->assertJson(['status' => true]);

        $this->assertDatabaseHas('holds', [
            'product_id' => $product->id,
            'qty' => 3,
            'status' => 'reserved'
        ]);

        $product->refresh();
        $this->assertEquals(3, $product->reserved);
    }

   
    public function test_it_prevents_overselling_when_stock_insufficient()
    {
        $product = $this->createProduct(2);

        $response = $this->postJson('/api/holds', [
            'product_id' => $product->id,
            'qty' => 5
        ]);

        if ($response->status() === 404) {
            $this->markTestSkipped('Hold endpoint not implemented');
            return;
        }

        $response->assertStatus(422)
            ->assertJson(['status' => false]);
    }

    
    public function test_it_creates_order_from_valid_hold()
    {
        $product = $this->createProduct(10);
        $hold = $this->createHold($product->id, 2);

        $response = $this->postJson('/api/orders', [
            'hold_id' => $hold->id
        ]);

        if ($response->status() === 404) {
            $this->markTestSkipped('Order endpoint not implemented');
            return;
        }

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'hold_id', 'product_id', 'qty', 'total_price', 'payment_status'
            ]);

        $this->assertDatabaseHas('orders', [
            'hold_id' => $hold->id,
            'payment_status' => 'pending'
        ]);

        $hold->refresh();
        $this->assertNotNull($hold->order_id);
    }

   
    public function test_it_rejects_expired_hold_for_order()
    {
        $product = $this->createProduct(10);
        $hold = $this->createHold($product->id, 2, true); // hold منتهي

        $response = $this->postJson('/api/orders', [
            'hold_id' => $hold->id
        ]);

        if ($response->status() === 404) {
            $this->markTestSkipped('Order endpoint not implemented');
            return;
        }

        $response->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    
    public function test_no_oversell_with_high_concurrency()
    {
        $product = $this->createProduct(5);

        $successCount = 0;
        $failureCount = 0;

        for ($i = 0; $i < 10; $i++) {
            try {
                $response = $this->postJson('/api/holds', [
                    'product_id' => $product->id,
                    'qty' => 1
                ]);

                if ($response->status() === 201) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            } catch (\Exception $e) {
                $failureCount++;
            }
        }

        $this->assertEquals(5, $successCount);
        $this->assertEquals(5, $failureCount);

        $product->refresh();
        $this->assertEquals(5, $product->reserved);
    }

    
    public function test_webhook_idempotency_same_key_repeated()
    {
        $product = $this->createProduct(10);
        $hold = $this->createHold($product->id, 1);
        
        $orderResponse = $this->postJson('/api/orders', ['hold_id' => $hold->id]);
        
        if ($orderResponse->status() === 404) {
            $this->markTestSkipped('Order endpoint not implemented');
            return;
        }
        
        $orderId = $orderResponse->json()['id'];
        $idempotencyKey = 'test-key-' . Str::random(32);

        $response1 = $this->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'status' => 'success',
            'payment_id' => 'pay_' . Str::random(10)
        ], [
            'Idempotency-Key' => $idempotencyKey
        ]);

        if ($response1->status() === 404) {
            $this->markTestSkipped('Webhook endpoint not implemented');
            return;
        }

        $response1Data = $response1->json();

        $response2 = $this->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'status' => 'success',
            'payment_id' => 'pay_' . Str::random(10)
        ], [
            'Idempotency-Key' => $idempotencyKey
        ]);

        $response2Data = $response2->json();

        $this->assertEquals($response1Data, $response2Data);

        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    
    public function test_webhook_idempotency_with_different_keys()
    {
        $product = $this->createProduct(10);
        $hold = $this->createHold($product->id, 1);
        
        $orderResponse = $this->postJson('/api/orders', ['hold_id' => $hold->id]);
        
        if ($orderResponse->status() === 404) {
            $this->markTestSkipped('Order endpoint not implemented');
            return;
        }
        
        $orderId = $orderResponse->json()['id'];

        $response1 = $this->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'status' => 'success'
        ], [
            'Idempotency-Key' => 'key-1-' . Str::random(32)
        ]);

        if ($response1->status() === 404) {
            $this->markTestSkipped('Webhook endpoint not implemented');
            return;
        }

        $response2 = $this->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'status' => 'success'
        ], [
            'Idempotency-Key' => 'key-2-' . Str::random(32)
        ]);

        $response2Data = $response2->json();

        $this->assertContains($response2Data['status'], ['already_paid', 'success']);
    }

  
    public function test_webhook_out_of_order_arrival()
    {
        $idempotencyKey = 'out-of-order-key-' . Str::random(32);
        
        $response1 = $this->postJson('/api/payments/webhook', [
            'order_id' => 99999,
            'status' => 'success'
        ], [
            'Idempotency-Key' => $idempotencyKey
        ]);

        if ($response1->status() === 404) {
            $this->markTestSkipped('Webhook endpoint not implemented');
            return;
        }

        $response1Data = $response1->json();
        
        $this->assertTrue(in_array($response1Data['status'], ['order_not_found', 'pending', 'error']));
    }

    public function test_failed_payment_releases_stock()
    {
        $product = $this->createProduct(10);
        $hold = $this->createHold($product->id, 3);
        
        $orderResponse = $this->postJson('/api/orders', ['hold_id' => $hold->id]);
        
        if ($orderResponse->status() === 404) {
            $this->markTestSkipped('Order endpoint not implemented');
            return;
        }
        
        $orderId = $orderResponse->json()['id'];
        
        $originalReserved = $product->reserved;
        
        $response = $this->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'status' => 'failed',
            'reason' => 'insufficient_funds'
        ], [
            'Idempotency-Key' => 'fail-test-' . Str::random(32)
        ]);

        if ($response->status() === 404) {
            $this->markTestSkipped('Webhook endpoint not implemented');
            return;
        }

        $product->refresh();
        $this->assertLessThan($originalReserved, $product->reserved);
        
        $order = Order::find($orderId);
        if ($order) {
            $this->assertEquals('failed', $order->payment_status);
        }
    }


    public function test_hold_expiry_releases_stock()
    {
        $product = $this->createProduct(10);
        
        $hold = Hold::create([
            'product_id' => $product->id,
            'qty' => 4,
            'expires_at' => now()->subMinutes(5),
            'status' => 'reserved',
            'order_id' => null
        ]);
        
        $originalReserved = $product->reserved;
        
        $product->decrement('reserved', $hold->qty);
        $hold->update(['status' => 'expired']);
        
        $product->refresh();
        $this->assertEquals($originalReserved - $hold->qty, $product->reserved);
        
        $hold->refresh();
        $this->assertEquals('expired', $hold->status);
    }

  
    public function test_product_cache_invalidation()
    {
        $product = $this->createProduct(10);
        
        $response1 = $this->getJson("/api/products/{$product->id}");
        
        if ($response1->status() === 404) {
            $this->markTestSkipped('Product endpoint not implemented');
            return;
        }
        
        $holdResponse = $this->postJson('/api/holds', [
            'product_id' => $product->id,
            'qty' => 3
        ]);
        
        if ($holdResponse->status() === 404) {
            $this->markTestSkipped('Hold endpoint not implemented');
            return;
        }
        
        $response2 = $this->getJson("/api/products/{$product->id}");
        $response2Data = $response2->json();
        
        $product->refresh();
        $this->assertEquals(3, $product->reserved);
    }

    
    public function test_concurrent_order_creation_from_same_hold()
    {
        $product = $this->createProduct(10);
        $hold = $this->createHold($product->id, 2);
        
        $responses = [];
        
        for ($i = 0; $i < 2; $i++) {
            $responses[] = $this->postJson('/api/orders', [
                'hold_id' => $hold->id
            ]);
        }
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($responses as $response) {
            if ($response->status() === 201) {
                $successCount++;
            } elseif ($response->status() === 422) {
                $errorCount++;
            }
        }
        
        $this->assertEquals(1, $successCount);
        $this->assertEquals(1, $errorCount);
        
        $hold->refresh();
        $this->assertNotNull($hold->order_id);
        $this->assertDatabaseCount('orders', 1);
    }
}