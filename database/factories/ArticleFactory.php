<?php

namespace Database\Factories;

use App\Enums\ArticleLayout;
use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Models\Article;
use App\Models\Author;
use App\Models\Site;
use App\Models\SiteCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(6);
        $site = Site::factory();

        return [
            'user_id' => User::factory(),
            'site_id' => $site,
            'site_category_id' => SiteCategory::factory()->for($site),
            'author_id' => Author::factory()->for($site),
            'uuid' => (string) Str::uuid(),
            'type' => fake()->randomElement(ArticleType::cases()),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('####'),
            'excerpt' => fake()->paragraph(),
            'keywords' => 'blog, cms, content',
            'content' => '<p>'.fake()->paragraphs(3, true).'</p>',
            'layout' => fake()->randomElement(ArticleLayout::cases()),
            'status' => ArticleStatus::Draft,
        ];
    }

    public function blog(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ArticleType::Blog,
        ]);
    }

    public function faq(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ArticleType::Faq,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ArticleStatus::Published,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ArticleStatus::Draft,
        ]);
    }
}
