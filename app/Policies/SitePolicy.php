<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;
use App\Support\SiteAccess;

class SitePolicy
{
    public function __construct(private SiteAccess $siteAccess) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Site $site): bool
    {
        return $this->siteAccess->canAccess($user, $site);
    }

    public function access(User $user, Site $site): bool
    {
        return $this->siteAccess->canAccess($user, $site);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Site $site): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Site $site): bool
    {
        return $user->isAdmin();
    }
}
