<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hold extends Model {
    use HasFactory;
    
    protected $fillable = [
        'product_id',
        'qty',
        'status',
        'expires_at',
        'order_id' 
    ];
    
    protected $dates = ['expires_at'];
    protected $casts = ['qty' => 'integer'];
    
    public function product() { 
        return $this->belongsTo(Product::class); 
    }
    
    public function order() { 
        return $this->hasOne(Order::class); 
    }
    
    public function isActive() { 
        return $this->status === 'active' && 
               $this->expires_at && 
               $this->expires_at->isFuture(); 
    }
}