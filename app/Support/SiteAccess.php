<?php

namespace App\Support;

use App\Models\Site;
use App\Models\SiteDelegation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SiteAccess
{
    /**
     * @return Collection<int, Site>
     */
    public function sitesFor(User $user): Collection
    {
        return $this->siteQueryFor($user)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Builder<Site>
     */
    public function siteQueryFor(User $user): Builder
    {
        if ($user->isAdmin()) {
            return Site::query();
        }

        return Site::query()
            ->active()
            ->whereHas('delegations', fn (Builder $query) => $query->where('user_id', $user->id));
    }

    public function canAccess(User $user, Site $site): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $site->is_active && $this->delegationFor($user, $site) !== null;
    }

    public function canWriteArticles(User $user, Site $site): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->canAccess($user, $site)
            && ($this->delegationFor($user, $site)?->can_write_articles ?? false);
    }

    public function canManageAuthors(User $user, Site $site): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->canAccess($user, $site)
            && ($this->delegationFor($user, $site)?->can_manage_authors ?? false);
    }

    public function canManageCategories(User $user, Site $site): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->canAccess($user, $site)
            && ($this->delegationFor($user, $site)?->can_manage_categories ?? false);
    }

    public function delegationFor(User $user, Site $site): ?SiteDelegation
    {
        return $user->siteDelegations->firstWhere('site_id', $site->id);
    }
}
