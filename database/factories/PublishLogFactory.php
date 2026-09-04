<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\PublishLog;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublishLog>
 */
class PublishLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'site_id' => Site::factory(),
            'request_payload' => [
                'title' => fake()->sentence(),
            ],
            'response_code' => 200,
            'response_payload' => '{"status":"ok"}',
            'status' => PublishStatus::Success,
            'error_message' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'response_code' => 500,
            'response_payload' => '{"error":"failed"}',
            'status' => PublishStatus::Failed,
            'error_message' => 'Remote endpoint returned an error.',
        ]);
    }
}
