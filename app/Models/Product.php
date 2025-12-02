<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model {
    use HasFactory; 
    
    protected $fillable = ['name', 'price', 'stock', 'reserved'];
    protected $casts = ['stock' => 'integer', 'reserved' => 'integer'];
    
    public function available() {
        return max(0, $this->stock - $this->reserved);
    }
    
    public function getAvailableStockAttribute() {
        return $this->available();
    }
}