<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Author>
 */
class AuthorFactory extends Factory
{
    protected $model = Author::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => fake()->name(),
            'credentials' => fake()->optional()->randomElement(['DNP, FNP-BC', 'RN, MSN', 'PhD, Nursing Education']),
            'bio' => fake()->optional()->paragraph(),
        ];
    }
}
