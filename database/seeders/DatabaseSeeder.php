<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Kennedy',
            'email' => 'Kennedy.fleekpapers@gmail.com',
            'password' => 'Password1-3',
        ]);

        $writer = User::factory()->writer()->create([
            'name' => 'Winfred',
            'email' => 'winfred@fleekdevelopers.com',
            'password' => 'Password1-3',
        ]);

        Site::factory()->count(2)->create();

        Article::factory()
            ->count(3)
            ->for($admin)
            ->blog()
            ->create();

        Article::factory()
            ->count(2)
            ->for($writer)
            ->faq()
            ->create();
    }
}
