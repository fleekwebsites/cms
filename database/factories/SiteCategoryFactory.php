<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteCategory>
 */
class SiteCategoryFactory extends Factory
{
    protected $model = SiteCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => fake()->words(2, true),
        ];
    }
}
