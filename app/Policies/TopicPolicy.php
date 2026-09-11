<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\Topic;
use App\Models\User;
use App\Support\SiteAccess;

class TopicPolicy
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

    public function delete(User $user, Topic $topic): bool
    {
        $topic->loadMissing('siteCategory.site');
        $site = $topic->siteCategory?->site;

        if ($site instanceof Site) {
            return $this->siteAccess->canManageCategories($user, $site);
        }

        return $user->isAdmin();
    }
}
