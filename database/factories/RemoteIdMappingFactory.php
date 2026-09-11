<?php

namespace Database\Factories;

use App\Enums\RemoteResource;
use App\Models\RemoteIdMapping;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RemoteIdMapping>
 */
class RemoteIdMappingFactory extends Factory
{
    protected $model = RemoteIdMapping::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'resource' => RemoteResource::Categories,
            'client_id' => fake()->numberBetween(100_000, 999_999),
            'remote_id' => fake()->numberBetween(1, 1000),
        ];
    }
}
