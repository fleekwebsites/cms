<?php

namespace App\Http\Controllers;

use App\Actions\PublishArticleToSites;
use App\Enums\ArticleStatus;
use App\Enums\PublishStatus;
use App\Http\Requests\StoreArticlePublicationRequest;
use App\Models\Article;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;

class ArticlePublicationController extends Controller
{
    public function store(
        StoreArticlePublicationRequest $request,
        Article $article,
        PublishArticleToSites $publisher,
    ): RedirectResponse {
        abort_unless($article->site_id !== null, 422, 'Assign a site before publishing.');

        $site = Site::query()
            ->active()
            ->findOrFail($article->site_id);

        $article->update([
            'status' => ArticleStatus::Published,
            'published_at' => $article->published_at ?? now(),
        ]);

        $logs = $publisher->handle(
            $article->fresh(['author:id,name,credentials,bio', 'site:id,name', 'siteCategory:id,name', 'user:id,name']),
            collect([$site]),
        );

        $log = $logs->first();
        $message = $log?->status === PublishStatus::Success
            ? "Published to {$site->name}."
            : "Publish attempt to {$site->name} failed.";

        return redirect()
            ->route('articles.show', $article)
            ->with('status', $message);
    }
}
