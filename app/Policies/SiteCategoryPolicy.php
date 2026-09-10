<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\SiteCategory;
use App\Models\User;

class SiteCategoryPolicy
{
    public function viewAny(User $user, Site $site): bool
    {
        return $user->isAdmin() || $site->is_active;
    }

    public function create(User $user, Site $site): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, SiteCategory $category): bool
    {
        return $user->isAdmin();
    }
}
