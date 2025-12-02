<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::create([
            'name' => 'iphone 17 Pro Max',
            'price' => 199.99,
            'stock' => 100,       
            'reserved' => 0,     
        ]);
    }
}
