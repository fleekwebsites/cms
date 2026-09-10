<?php

namespace App\Policies;

use App\Models\Author;
use App\Models\Site;
use App\Models\User;

class AuthorPolicy
{
    public function viewAny(User $user, ?Site $site = null): bool
    {
        if ($site === null) {
            return true;
        }

        return $user->isAdmin() || $site->is_active;
    }

    public function view(User $user, Author $author): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Author $author): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Author $author): bool
    {
        return $user->isAdmin();
    }
}
