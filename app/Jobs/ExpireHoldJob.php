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
    public function handle(StockService $stockService) {
        $hold = Hold::find($this->hold->id);
        if (!$hold) return;
        if ($hold->status !== 'active') return;
        if ($hold->expires_at && $hold->expires_at->isPast()) {
            $ok = $stockService->releaseHold($hold);
            Log::info('hold.expired', ['hold_id'=> $hold->id, 'released' => $ok]);
        }
    }
}
