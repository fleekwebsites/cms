<?php

namespace App\Http\Controllers;

use App\Enums\ArticleStatus;
use App\Http\Requests\StoreArticleCompletionRequest;
use App\Http\Requests\StoreArticlePublicationRequest;
use App\Models\Site;
use App\Support\ArticleContentFormatter;
use App\Support\RemoteRecord;
use App\Support\RemoteSendResult;
use App\Support\RemoteSiteGateway;
use App\Support\SiteAccess;
use Illuminate\Http\RedirectResponse;

class ArticlePublicationController extends Controller
{
    public function __construct(
        private RemoteSiteGateway $gateway,
        private ArticleContentFormatter $contentFormatter,
        private SiteAccess $siteAccess,
    ) {}

    public function complete(
        StoreArticleCompletionRequest $request,
        Site $site,
        string $articleUuid,
    ): RedirectResponse {
        abort_unless($this->siteAccess->canWriteArticles($request->user(), $site), 403);

        $article = $this->findOwnedArticle($request, $site, $articleUuid);

        if ($article->status() !== ArticleStatus::Draft) {
            return redirect()
                ->route('sites.articles.show', [$site, $articleUuid])
                ->with('error', 'Only draft articles can be marked as complete.');
        }

        $payload = $this->payloadForStatus($site, $article, ArticleStatus::Complete);

        return $this->respondToSend(
            $site,
            $articleUuid,
            $this->gateway->sendArticle($site, $payload, $request->user()->id),
            success: 'Article marked as complete.',
        );
    }

    public function store(
        StoreArticlePublicationRequest $request,
        Site $site,
        string $articleUuid,
    ): RedirectResponse {
        abort_unless($this->siteAccess->canWriteArticles($request->user(), $site), 403);

        $article = $this->findOwnedArticle($request, $site, $articleUuid);

        if (! $article->status()->isReadyToPublish()) {
            return redirect()
                ->route('sites.articles.show', [$site, $articleUuid])
                ->with('error', 'Mark the article as complete before publishing.');
        }

        $payload = $this->payloadForStatus($site, $article, ArticleStatus::Published);

        return $this->respondToSend(
            $site,
            $articleUuid,
            $this->gateway->sendArticle($site, $payload, $request->user()->id),
            success: "Published to {$site->name}.",
            failure: "Publish attempt to {$site->name} failed.",
        );
    }

    private function findOwnedArticle(
        StoreArticleCompletionRequest|StoreArticlePublicationRequest $request,
        Site $site,
        string $articleUuid,
    ): RemoteRecord {
        $fetch = $this->gateway->fetchArticle($site, $articleUuid);
        $article = $fetch->items->first();

        if ($article === null) {
            abort(404);
        }

        if (! $request->user()->isAdmin() && $article->int('editor_user_id') !== $request->user()->id) {
            abort(404);
        }

        return $article;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForStatus(Site $site, RemoteRecord $article, ArticleStatus $status): array
    {
        $payload = [
            ...$article->attributes,
            'content' => $this->contentFormatter->forPublish($article->string('content') ?? ''),
            'status' => $status->value,
            'site_id' => $site->id,
            'site_name' => $site->name,
        ];

        if ($status === ArticleStatus::Published) {
            $payload['published_at'] = $article->string('published_at') ?? now()->utc()->format('Y-m-d H:i:s');
        } else {
            unset($payload['published_at']);
        }

        return $payload;
    }

    private function respondToSend(
        Site $site,
        string $articleUuid,
        RemoteSendResult $result,
        string $success,
        ?string $failure = null,
    ): RedirectResponse {
        if ($result->successful) {
            return redirect()
                ->route('sites.articles.show', [$site, $articleUuid])
                ->with('status', $success);
        }

        if ($result->queued) {
            return redirect()
                ->route('sites.articles.show', [$site, $articleUuid])
                ->with('warning', $result->message);
        }

        return redirect()
            ->route('sites.articles.show', [$site, $articleUuid])
            ->with('error', $result->message ?? $failure ?? 'The remote site rejected the request.');
    }
}
