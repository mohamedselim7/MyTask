<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class HoldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'qty' => $this->faker->numberBetween(1, 5),
            'expires_at' => now()->addMinutes(10),
            'status' => 'reserved',
        ];
    }
    
    public function expired()
    {
        return $this->state(function (array $attributes) {
            return [
                'expires_at' => now()->subMinutes(5),
            ];
        });
    }
}