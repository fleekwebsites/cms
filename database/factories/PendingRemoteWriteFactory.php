<?php

namespace Database\Factories;

use App\Enums\PendingRemoteWriteStatus;
use App\Enums\RemoteResource;
use App\Models\PendingRemoteWrite;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PendingRemoteWrite>
 */
class PendingRemoteWriteFactory extends Factory
{
    protected $model = PendingRemoteWrite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'user_id' => User::factory(),
            'resource' => RemoteResource::Articles,
            'resource_key' => fake()->uuid(),
            'method' => 'POST',
            'payload' => [
                'uuid' => fake()->uuid(),
                'title' => fake()->sentence(4),
                'type' => 'blog',
                'layout' => 'default',
                'status' => 'draft',
                'content' => '<p>Hello</p>',
                'content_format' => 'html',
            ],
            'idempotency_key' => fake()->uuid(),
            'status' => PendingRemoteWriteStatus::Pending,
            'attempts' => 0,
        ];
    }
}
