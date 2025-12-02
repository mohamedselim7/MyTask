<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model {
    protected $fillable = ['name','price','stock','reserved'];
    protected $casts = ['stock' => 'integer', 'reserved' => 'integer'];
    
    public function available() {
        return max(0, $this->stock - $this->reserved);
    }
}
