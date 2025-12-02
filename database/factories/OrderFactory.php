<?php

namespace Database\Factories;

use App\Models\Hold;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hold_id' => Hold::factory(),
            'product_id' => function (array $attributes) {
                return Hold::find($attributes['hold_id'])->product_id;
            },
            'qty' => function (array $attributes) {
                return Hold::find($attributes['hold_id'])->qty;
            },
            'total_price' => $this->faker->randomFloat(2, 10, 500),
            'payment_status' => 'pending',
        ];
    }
    
    public function paid()
    {
        return $this->state(function (array $attributes) {
            return [
                'payment_status' => 'paid',
                'paid_at' => now(),
            ];
        });
    }
    
    public function failed()
    {
        return $this->state(function (array $attributes) {
            return [
                'payment_status' => 'failed',
                'failed_at' => now(),
            ];
        });
    }
}