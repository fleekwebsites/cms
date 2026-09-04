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
        $sites = Site::query()
            ->active()
            ->whereIn('id', $request->validated('site_ids'))
            ->orderBy('name')
            ->get();

        $article->update([
            'status' => ArticleStatus::Published,
            'published_at' => $article->published_at ?? now(),
        ]);

        $logs = $publisher->handle($article->fresh(['authorName:id,name', 'user:id,name']), $sites);

        $successful = $logs->where('status', PublishStatus::Success)->count();
        $failed = $logs->where('status', PublishStatus::Failed)->count();

        $message = "Published to {$successful} site".($successful === 1 ? '' : 's').'.';

        if ($failed > 0) {
            $message .= " {$failed} delivery".($failed === 1 ? '' : 's').' failed.';
        }

        return redirect()
            ->route('articles.show', $article)
            ->with('status', $message);
    }
}
