<?php

namespace App\Repositories;

use App\Models\IdempotencyKey;

class IdempotencyRepository
{
    public function findByKey(string $key): ?IdempotencyKey
    {
        return IdempotencyKey::where('key', $key)->first();
    }
    
    public function create(array $data): IdempotencyKey
    {
        return IdempotencyKey::create($data);
    }
    
    public function updateOrCreate(array $attributes, array $values): IdempotencyKey
    {
        return IdempotencyKey::updateOrCreate($attributes, $values);
    }
    
    public function createOrGet(string $key, array $data): IdempotencyKey
    {
        return IdempotencyKey::firstOrCreate(
            ['key' => $key],
            [
                'request_data' => $data,
                'response_data' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
    }
    public function saveResponse(IdempotencyKey $idempotency, array $response): void
    {
        $idempotency->update([
            'response_data' => $response,
            'updated_at' => now()
        ]);
    }
}