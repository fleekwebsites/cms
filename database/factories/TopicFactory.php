<?php

namespace Database\Factories;

use App\Models\SiteCategory;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_category_id' => SiteCategory::factory(),
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
