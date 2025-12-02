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
        ->chunkById(100, function ($holds) {
            foreach ($holds as $hold) {
                DB::transaction(function () use ($hold) {
                    $hold->lockForUpdate();
                    if (!$hold->order && $hold->status === 'reserved') {
                        $hold->product->increment('available_stock', $hold->qty);
                        $hold->update(['status' => 'expired']);
                        
                        Log::info('Hold expired and stock released', [
                            'hold_id' => $hold->id,
                            'product_id' => $hold->product_id,
                            'qty' => $hold->qty
                        ]);
                    }
                });
            }
        });
}
    }

