<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hold extends Model {
    protected $fillable = ['product_id','qty','status','expires_at'];
    protected $dates = ['expires_at'];
    protected $casts = ['qty' => 'integer'];
    public function product() { return $this->belongsTo(Product::class); }
    public function isActive() { return $this->status === 'active' && $this->expires_at && $this->expires_at->isFuture(); }
}
