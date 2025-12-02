<?php 
namespace App\Jobs;
use App\Models\Hold;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\StockService;
use Illuminate\Support\Facades\Log;

class ExpireHoldJob implements ShouldQueue {
    use InteractsWithQueue, Queueable, SerializesModels;
    public $hold;
    public function __construct(Hold $hold) { $this->hold = $hold; }
public function handle()
{
    Hold::where('expires_at', '<', now())
        ->where('status', 'reserved')
        ->whereNull('order_id')
        ->chunkById(100, function ($holds) {
            foreach ($holds as $hold) {
                DB::transaction(function () use ($hold) {
                    $freshHold = Hold::where('id', $hold->id)
                        ->where('status', 'reserved')
                        ->whereNull('order_id')
                        ->lockForUpdate()
                        ->first();
                    
                    if (!$freshHold) {
                        return; 
                    }
                    $product = Product::where('id', $freshHold->product_id)
                        ->lockForUpdate()
                        ->first();
                    if ($product) {
                        $product->increment('available_stock', $freshHold->qty);
                        Cache::forget("product.{$product->id}");
                        
                        Log::info('Stock released from expired hold', [
                            'hold_id' => $freshHold->id,
                            'product_id' => $product->id,
                            'qty' => $freshHold->qty
                        ]);
                    }
                    $freshHold->update(['status' => 'expired']);
                });
            }
        });
}
    }

