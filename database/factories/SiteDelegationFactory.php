<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteDelegation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteDelegation>
 */
class SiteDelegationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'site_id' => Site::factory(),
            'can_write_articles' => true,
            'can_manage_authors' => false,
            'can_manage_categories' => false,
        ];
    }

    public function manageAuthors(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_manage_authors' => true,
        ]);
    }

    public function manageCategories(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_manage_categories' => true,
        ]);
    }
}
