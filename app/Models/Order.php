<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model {
    protected $fillable = [
        'hold_id', 
        'product_id',
        'qty',
        'total_price',
        'payment_status',
        'paid_at',
        'failed_at'
    ];
    
    protected $casts = [
        'qty' => 'integer',
        'total_price' => 'decimal:2'
    ];
    
    public function hold(){ 
        return $this->belongsTo(Hold::class); 
    }
    
    public function product() {
        return $this->belongsTo(Product::class);
    }
}