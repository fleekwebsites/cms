<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Uses explicit records so this works in production where
     * fakerphp/faker is not installed (--no-dev).
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'Kennedy.fleekpapers@gmail.com'],
            [
                'name' => 'Kennedy',
                'password' => 'Password1-3',
                'role' => Role::Admin,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'winfred@fleekdevelopers.com'],
            [
                'name' => 'Winfred',
                'password' => 'Password1-3',
                'role' => Role::Writer,
            ],
        );
    }
}
