<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model {
    protected $fillable = ['hold_id','status','payment_meta'];
    protected $casts = ['payment_meta' => 'array'];
    public function hold(){ return $this->belongsTo(Hold::class); }
}
