<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Hold;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FlashSaleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_product_with_stock()
{
    $product = Product::create([
        'name' => 'Flash Sale Item',
        'price' => 99.99,
        'stock' => 100, 
        'reserved' => 0
    ]);

    $response = $this->getJson("/api/products/{$product->id}");

    $response->assertStatus(200)
        ->assertJson([
            'id' => $product->id,
            'name' => 'Flash Sale Item',
            'price' => 99.99
        ]);
}

    /** @test */
    public function it_creates_hold_and_reduces_availability()
    {
        $product = Product::factory()->create(['available_stock' => 10]);

        $response = $this->postJson('/api/holds', [
            'product_id' => $product->id,
            'qty' => 3
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'hold_id',
                'expires_at'
            ]);

        $this->assertDatabaseHas('holds', [
            'product_id' => $product->id,
            'qty' => 3
        ]);

        $product->refresh();
        $this->assertEquals(7, $product->available_stock);
    }

    /** @test */
    public function it_prevents_overselling_when_stock_insufficient()
    {
        $product = Product::factory()->create(['available_stock' => 2]);

        $response = $this->postJson('/api/holds', [
            'product_id' => $product->id,
            'qty' => 5
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Insufficient stock available']);
    }

    /** @test */
    public function it_creates_order_from_valid_hold()
    {
        $product = Product::factory()->create(['available_stock' => 10]);
        
        $hold = Hold::factory()->create([
            'product_id' => $product->id,
            'qty' => 2,
            'expires_at' => now()->addMinutes(5)
        ]);

        $response = $this->postJson('/api/orders', [
            'hold_id' => $hold->id
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'hold_id',
                'total_price',
                'payment_status'
            ]);

        $this->assertDatabaseHas('orders', [
            'hold_id' => $hold->id,
            'payment_status' => 'pending'
        ]);
    }

    /** @test */
    public function it_rejects_expired_hold_for_order()
    {
        $hold = Hold::factory()->create([
            'expires_at' => now()->subMinutes(1)
        ]);

        $response = $this->postJson('/api/orders', [
            'hold_id' => $hold->id
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Invalid or expired hold']);
    }

    /** @test */
    public function test_no_oversell_with_high_concurrency()
    {
        Http::fake();
        
        $product = Product::factory()->create(['available_stock' => 5]);
        
        $requests = [];
        
        for ($i = 0; $i < 10; $i++) {
            $requests[] = Http::async()->post('/api/holds', [
                'product_id' => $product->id,
                'qty' => 1
            ]);
        }
        
        $responses = [];
        foreach ($requests as $request) {
            try {
                $responses[] = $request->wait();
            } catch (\Exception $e) {
            }
        }
        
        $successfulCount = 0;
        foreach ($responses as $response) {
            if ($response && $response->successful()) {
                $successfulCount++;
            }
        }
        
        $this->assertEquals(5, $successfulCount);
        
        $product->refresh();
        $this->assertEquals(0, $product->available_stock);
        
        $holdsCount = Hold::where('product_id', $product->id)->count();
        $this->assertEquals(5, $holdsCount);
    }

    /** @test */
    public function test_webhook_idempotency_same_key_repeated()
    {
        $order = Order::factory()->create(['payment_status' => 'pending']);
        
        $idempotencyKey = 'test-key-' . Str::random(32);
        
        $response1 = $this->postJson('/api/payments/webhook', [
            'order_id' => $order->id,
            'status' => 'success',
            'payment_id' => 'pay_' . Str::random(10)
        ], [
            'Idempotency-Key' => $idempotencyKey
        ]);
        
        $response1->assertStatus(200);
        $response1Data = $response1->json();
        
        $response2 = $this->postJson('/api/payments/webhook', [
            'order_id' => $order->id,
            'status' => 'success',
            'payment_id' => 'pay_' . Str::random(10)
        ], [
            'Idempotency-Key' => $idempotencyKey
        ]);
        
        $response2->assertStatus(200);
        $response2Data = $response2->json();
        
        $this->assertEquals($response1Data, $response2Data);
        
        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        
        $this->assertDatabaseCount('idempotency_keys', 1);
        $this->assertDatabaseHas('idempotency_keys', [
            'key' => $idempotencyKey
        ]);
    }

    /** @test */
    public function test_webhook_idempotency_with_different_keys()
    {
        $order = Order::factory()->create(['payment_status' => 'pending']);
        
        $response1 = $this->postJson('/api/payments/webhook', [
            'order_id' => $order->id,
            'status' => 'success'
        ], [
            'Idempotency-Key' => 'key-1-' . Str::random(32)
        ]);
        
        $response1->assertStatus(200);
        
        $response2 = $this->postJson('/api/payments/webhook', [
            'order_id' => $order->id,
            'status' => 'success'
        ], [
            'Idempotency-Key' => 'key-2-' . Str::random(32)
        ]);
        
        $response2->assertStatus(200);
        $response2Data = $response2->json();
        
        $this->assertEquals('already_paid', $response2Data['status']);
    }

    /** @test */
    public function test_webhook_out_of_order_arrival()
    {
        $idempotencyKey = 'out-of-order-key-' . Str::random(32);
        
        $response1 = $this->postJson('/api/payments/webhook', [
            'order_id' => 99999, 
            'status' => 'success'
        ], [
            'Idempotency-Key' => $idempotencyKey
        ]);
        
        $response1->assertStatus(200);
        $response1Data = $response1->json();
        
        $this->assertEquals('order_not_found', $response1Data['status']);
        
        $order = Order::factory()->create(['id' => 99999, 'payment_status' => 'pending']);
        
        $response2 = $this->postJson('/api/payments/webhook', [
            'order_id' => $order->id,
            'status' => 'success'
        ], [
            'Idempotency-Key' => $idempotencyKey
        ]);
        
        $response2Data = $response2->json();
        $this->assertEquals($response1Data, $response2Data);
        
        $order->refresh();
        $this->assertEquals('pending', $order->payment_status);
    }

    /** @test */
    public function test_concurrent_idempotent_webhooks()
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('PCNTL extension not available');
            return;
        }
        
        $order = Order::factory()->create(['payment_status' => 'pending']);
        $idempotencyKey = 'concurrent-test-' . Str::random(32);
        
        $pids = [];
        $results = [];
        
        for ($i = 0; $i < 5; $i++) {
            $pid = pcntl_fork();
            
            if ($pid == -1) {
                die('Could not fork');
            } elseif ($pid) {
                $pids[] = $pid;
            } else {
                usleep(rand(0, 100000)); 
                
                $client = new \GuzzleHttp\Client([
                    'base_uri' => 'http://localhost',
                    'timeout' => 5,
                    'http_errors' => false
                ]);
                
                try {
                    $response = $client->post('/api/payments/webhook', [
                        'headers' => [
                            'Idempotency-Key' => $idempotencyKey,
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json'
                        ],
                        'json' => [
                            'order_id' => $order->id,
                            'status' => 'success'
                        ]
                    ]);
                    
                    $results[] = [
                        'status' => $response->getStatusCode(),
                        'body' => json_decode($response->getBody(), true)
                    ];
                } catch (\Exception $e) {
                    $results[] = ['error' => $e->getMessage()];
                }
                
                exit(0);
            }
        }
        
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        
        
        $responses = [];
        
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/payments/webhook', [
                'order_id' => $order->id,
                'status' => 'success'
            ], [
                'Idempotency-Key' => $idempotencyKey
            ]);
            
            $responses[] = $response->json();
        }
        
        $firstResponse = $responses[0];
        foreach ($responses as $response) {
            $this->assertEquals($firstResponse, $response);
        }
        
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    /** @test */
    public function test_failed_payment_releases_stock()
    {
        $product = Product::factory()->create(['available_stock' => 10]);
        
        $hold = Hold::factory()->create([
            'product_id' => $product->id,
            'qty' => 3
        ]);
        
        $order = Order::factory()->create([
            'hold_id' => $hold->id,
            'product_id' => $product->id,
            'qty' => 3,
            'payment_status' => 'pending'
        ]);
        
        $originalStock = $product->available_stock;
        
        $response = $this->postJson('/api/payments/webhook', [
            'order_id' => $order->id,
            'status' => 'failed',
            'reason' => 'insufficient_funds'
        ], [
            'Idempotency-Key' => 'fail-test-' . Str::random(32)
        ]);
        
        $response->assertStatus(200);
        
        $product->refresh();
        $this->assertEquals($originalStock, $product->available_stock);
        
        $order->refresh();
        $this->assertEquals('failed', $order->payment_status);
    }

    /** @test */
    public function test_hold_expiry_releases_stock()
    {
        $product = Product::factory()->create(['available_stock' => 10]);
        
        $hold = Hold::factory()->create([
            'product_id' => $product->id,
            'qty' => 4,
            'expires_at' => now()->subMinutes(5), 
            'status' => 'reserved'
        ]);
        
        $job = new \App\Jobs\ExpireHolds();
        $job->handle();
        
        $product->refresh();
        $this->assertEquals(10, $product->available_stock);
        
        $hold->refresh();
        $this->assertEquals('expired', $hold->status);
    }

    /** @test */
    public function test_product_cache_invalidation()
    {
        $product = Product::factory()->create([
            'available_stock' => 10,
            'price' => 100.00
        ]);
        
        $response1 = $this->getJson("/api/products/{$product->id}");
        $response1->assertStatus(200);
        
        $this->postJson('/api/holds', [
            'product_id' => $product->id,
            'qty' => 3
        ]);
        
        $response2 = $this->getJson("/api/products/{$product->id}");
        $response2->assertStatus(200);
        $response2Data = $response2->json();
        $this->assertEquals(7, $response2Data['available_stock']);
    }
}