<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model {
    use HasFactory; 
    
    protected $fillable = [
        'hold_id',
        'product_id',
        'qty',
        'total_price',
        'payment_status',
        'payment_meta',
        'paid_at',
        'failed_at'
    ];
    
    protected $casts = [
        'payment_meta' => 'array',
        'qty' => 'integer',
        'total_price' => 'decimal:2'
    ];
    
    public function hold(){ 
        return $this->belongsTo(Hold::class); 
    }
    
    public function product() {
        return $this->belongsTo(Product::class);
    }
    
    public function getStatusAttribute() {
        return $this->payment_status;
    }
    
    public function setStatusAttribute($value) {
        $this->attributes['payment_status'] = $value;
    }
}