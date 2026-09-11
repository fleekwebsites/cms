<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\SiteCategory;
use App\Models\User;
use App\Support\SiteAccess;

class SiteCategoryPolicy
{
    public function __construct(private SiteAccess $siteAccess) {}

    public function viewAny(User $user, Site $site): bool
    {
        return $this->siteAccess->canAccess($user, $site);
    }

    public function create(User $user, Site $site): bool
    {
        return $this->siteAccess->canWriteArticles($user, $site)
            || $this->siteAccess->canManageCategories($user, $site);
    }

    public function update(User $user, SiteCategory|Site $subject): bool
    {
        if ($subject instanceof Site) {
            return $this->siteAccess->canWriteArticles($user, $subject)
                || $this->siteAccess->canManageCategories($user, $subject);
        }

        $subject->loadMissing('site');

        if ($subject->site instanceof Site) {
            return $this->siteAccess->canWriteArticles($user, $subject->site)
                || $this->siteAccess->canManageCategories($user, $subject->site);
        }

        return $user->isAdmin();
    }

    public function delete(User $user, SiteCategory|Site $subject): bool
    {
        if ($subject instanceof Site) {
            return $this->siteAccess->canManageCategories($user, $subject);
        }

        $subject->loadMissing('site');

        if ($subject->site instanceof Site) {
            return $this->siteAccess->canManageCategories($user, $subject->site);
        }

        return $user->isAdmin();
    }
}
