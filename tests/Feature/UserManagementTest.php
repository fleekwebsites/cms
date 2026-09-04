<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_writer_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'New Writer',
                'email' => 'new-writer@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => Role::Writer->value,
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'new-writer@example.com',
            'role' => Role::Writer->value,
        ]);
    }

    public function test_writer_cannot_access_user_management(): void
    {
        $writer = User::factory()->writer()->create();

        $this->actingAs($writer)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('users.destroy', $admin))
            ->assertForbidden();
    }
}
