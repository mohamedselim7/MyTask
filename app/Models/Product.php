<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model {
    protected $fillable = ['name', 'price', 'available_stock'];
    protected $casts = ['available_stock' => 'integer'];
    
    public function available() {
        return $this->available_stock;
    }
}