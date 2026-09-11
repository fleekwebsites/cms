<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\Site;
use App\Models\User;
use App\Support\SiteAccess;

class ArticlePolicy
{
    public function __construct(private SiteAccess $siteAccess) {}

    public function viewAny(User $user, ?Site $site = null): bool
    {
        if ($site === null) {
            return true;
        }

        return $this->siteAccess->canAccess($user, $site);
    }

    public function view(User $user, Article $article): bool
    {
        if ($article->site_id === null) {
            return $user->isAdmin() || $article->user_id === $user->id;
        }

        $article->loadMissing('site');

        if ($article->site instanceof Site && ! $this->siteAccess->canAccess($user, $article->site)) {
            return false;
        }

        return $user->isAdmin() || $article->user_id === $user->id;
    }

    public function create(User $user, ?Site $site = null): bool
    {
        if ($site === null) {
            return $user->isAdmin() || $user->siteDelegations()->where('can_write_articles', true)->exists();
        }

        return $this->siteAccess->canWriteArticles($user, $site);
    }

    public function update(User $user, Article $article): bool
    {
        if (! $this->view($user, $article)) {
            return false;
        }

        $article->loadMissing('site');

        if ($article->site instanceof Site && ! $this->siteAccess->canWriteArticles($user, $article->site)) {
            return false;
        }

        return $user->isAdmin() || $article->user_id === $user->id;
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->isAdmin() && $this->view($user, $article);
    }

    public function publish(User $user, Article|Site $subject): bool
    {
        if ($subject instanceof Site) {
            return $this->siteAccess->canWriteArticles($user, $subject);
        }

        return $this->update($user, $subject);
    }
}
