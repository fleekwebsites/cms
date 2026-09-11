<?php

namespace App\Policies;

use App\Models\Author;
use App\Models\Site;
use App\Models\User;
use App\Support\SiteAccess;

class AuthorPolicy
{
    public function __construct(private SiteAccess $siteAccess) {}

    public function viewAny(User $user, ?Site $site = null): bool
    {
        if ($site === null) {
            return $user->isAdmin();
        }

        return $this->siteAccess->canAccess($user, $site);
    }

    public function view(User $user, Author $author): bool
    {
        $author->loadMissing('site');

        if ($author->site instanceof Site) {
            return $this->siteAccess->canAccess($user, $author->site);
        }

        return $user->isAdmin();
    }

    public function create(User $user, ?Site $site = null): bool
    {
        if ($site === null) {
            return $user->isAdmin();
        }

        return $this->siteAccess->canManageAuthors($user, $site);
    }

    public function update(User $user, Author|Site $subject): bool
    {
        if ($subject instanceof Site) {
            return $this->siteAccess->canManageAuthors($user, $subject);
        }

        $subject->loadMissing('site');

        if ($subject->site instanceof Site) {
            return $this->siteAccess->canManageAuthors($user, $subject->site);
        }

        return $user->isAdmin();
    }

    public function delete(User $user, Author|Site $subject): bool
    {
        return $this->update($user, $subject);
    }
}
