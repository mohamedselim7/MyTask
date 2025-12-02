<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MetricsService
{
    public static function increment(string $metric, array $tags = [])
    {
        $key = "metrics.{$metric}." . date('Y-m-d-H');
        
        Cache::increment($key);
        
        Log::channel('metrics')->info($metric, array_merge($tags, [
            'value' => Cache::get($key, 0),
            'timestamp' => now()->toISOString()
        ]));
    }
    
    public static function timer(string $metric, float $seconds, array $tags = [])
    {
        Log::channel('metrics')->info($metric, array_merge($tags, [
            'duration_seconds' => $seconds,
            'timestamp' => now()->toISOString()
        ]));
    }
}

MetricsService::increment('hold.created', [
    'product_id' => $product->id,
    'qty' => $request->qty
]);

$start = microtime(true);
$duration = microtime(true) - $start;
MetricsService::timer('webhook.processing_time', $duration, [
    'status' => $request->input('status')
]);