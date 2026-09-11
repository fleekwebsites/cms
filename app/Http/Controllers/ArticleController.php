<?php

namespace App\Http\Controllers;

use App\Enums\ArticleLayout;
use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Models\Article;
use App\Models\Site;
use App\Models\SiteCategory;
use App\Models\User;
use App\Support\ArticleContentFormatter;
use App\Support\RemoteArticlePayload;
use App\Support\RemoteFetchResult;
use App\Support\RemoteMediaUrlResolver;
use App\Support\RemoteRecord;
use App\Support\RemoteSiteGateway;
use App\Support\SiteAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function __construct(
        private RemoteSiteGateway $gateway,
        private RemoteArticlePayload $payloadBuilder,
        private SiteAccess $siteAccess,
        private RemoteMediaUrlResolver $mediaUrlResolver,
    ) {}

    public function index(Request $request, Site $site): View
    {
        $this->authorize('viewAny', [Article::class, $site]);

        $fetch = $this->gateway->fetchArticles(
            $site,
            $request->filled('type') ? $request->string('type')->toString() : null,
            $request->filled('status') ? $request->string('status')->toString() : null,
        );

        $articles = $this->gateway->mergePendingArticles(
            $site,
            $fetch->reachable ? $fetch->items : collect(),
        );

        $articles = $this->filterArticlesForUser($request->user(), $articles);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 15;
        $paginated = new LengthAwarePaginator(
            $articles->forPage($page, $perPage)->values(),
            $articles->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('articles.index', [
            'site' => $site,
            'articles' => $paginated,
            'remoteReachable' => $fetch->reachable,
            'remoteMessage' => $fetch->message,
        ]);
    }

    public function create(Request $request, Site $site): View|RedirectResponse
    {
        $this->authorize('create', [Article::class, $site]);

        $formData = $this->formData($request, $site);

        if (! $formData['remoteReachable']) {
            return redirect()
                ->route('sites.articles.index', $site)
                ->with('error', $formData['remoteMessage']);
        }

        return view('articles.create', [
            'site' => $site,
            ...$formData,
        ]);
    }

    public function store(StoreArticleRequest $request, Site $site): RedirectResponse
    {
        $payload = $this->payloadBuilder->fromRequest(
            $request->validated(),
            $site,
            $request->user(),
            featuredImage: $request->file('featured_image') instanceof UploadedFile
                ? $request->file('featured_image')
                : null,
        );

        $result = $this->gateway->sendArticle($site, $payload, $request->user()->id);

        if ($result->successful) {
            return redirect()
                ->route('sites.articles.show', [$site, $payload['uuid']])
                ->with('status', 'Article saved on the remote site.');
        }

        if ($result->queued) {
            return redirect()
                ->route('sites.articles.show', [$site, $payload['uuid']])
                ->with('warning', $result->message);
        }

        return redirect()
            ->back()
            ->withInput()
            ->with('error', $result->message ?? 'Unable to save the article.');
    }

    public function show(Request $request, Site $site, string $articleUuid): View
    {
        $article = $this->findArticle($site, $articleUuid);

        if ($article === null) {
            abort(404);
        }

        $this->authorizeArticle($request->user(), $site, $article, 'view');

        $article = $this->mediaUrlResolver->resolveArticle($article, $site);

        $authors = $this->gateway->fetchAuthors($site);
        $categories = $this->gateway->fetchCategories($site);
        $topics = $article->int('site_category_id') !== null
            ? $this->gateway->fetchTopics($site, $article->int('site_category_id'))
            : RemoteFetchResult::success(collect());

        return view('articles.show', [
            'site' => $site,
            'article' => $article,
            'previewContent' => app(ArticleContentFormatter::class)->normalize($article->string('content') ?? ''),
            'authorRecord' => $authors->items->first(fn (RemoteRecord $record): bool => $record->int('id') === $article->int('author_id')),
            'categoryName' => $article->categoryName($categories->items),
            'topicName' => $article->topicName($topics->items),
            'pendingSync' => $article->bool('pending_sync'),
        ]);
    }

    public function edit(Request $request, Site $site, string $articleUuid): View|RedirectResponse
    {
        $article = $this->findArticle($site, $articleUuid);

        if ($article === null) {
            abort(404);
        }

        $this->authorizeArticle($request->user(), $site, $article, 'update');

        $article = $this->mediaUrlResolver->resolveArticle($article, $site);

        $formData = $this->formData($request, $site, $article);

        if (! $formData['remoteReachable']) {
            return redirect()
                ->route('sites.articles.show', [$site, $articleUuid])
                ->with('error', $formData['remoteMessage']);
        }

        return view('articles.edit', [
            'site' => $site,
            'article' => $article,
            ...$formData,
        ]);
    }

    public function update(UpdateArticleRequest $request, Site $site, string $articleUuid): RedirectResponse
    {
        $article = $this->findArticle($site, $articleUuid);

        if ($article === null) {
            abort(404);
        }

        $this->authorizeArticle($request->user(), $site, $article, 'update');

        $payload = $this->payloadBuilder->fromRequest(
            $request->validated(),
            $site,
            $request->user(),
            $article,
            $request->file('featured_image') instanceof UploadedFile
                ? $request->file('featured_image')
                : null,
        );

        $result = $this->gateway->sendArticle($site, $payload, $request->user()->id);

        if ($result->successful) {
            return redirect()
                ->route('sites.articles.show', [$site, $articleUuid])
                ->with('status', 'Article updated on the remote site.');
        }

        if ($result->queued) {
            return redirect()
                ->route('sites.articles.show', [$site, $articleUuid])
                ->with('warning', $result->message);
        }

        return redirect()
            ->back()
            ->withInput()
            ->with('error', $result->message ?? 'Unable to update the article.');
    }

    public function destroy(Request $request, Site $site, string $articleUuid): RedirectResponse
    {
        $article = $this->findArticle($site, $articleUuid);

        if ($article === null) {
            abort(404);
        }

        $this->authorizeArticle($request->user(), $site, $article, 'delete');

        $result = $this->gateway->deleteArticle($site, $articleUuid, $request->user()->id);

        if ($result->successful) {
            return redirect()
                ->route('sites.articles.index', $site)
                ->with('status', 'Article deleted from the remote site.');
        }

        if ($result->queued) {
            return redirect()
                ->route('sites.articles.index', $site)
                ->with('warning', $result->message);
        }

        return redirect()
            ->back()
            ->with('error', $result->message ?? 'Unable to delete the article.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, Site $site, ?RemoteRecord $article = null): array
    {
        $categories = $this->gateway->fetchCategories($site);
        $authors = $this->gateway->fetchAuthors($site);
        $categoryId = (int) old('site_category_id', $article?->int('site_category_id'));
        $topics = $categoryId > 0
            ? $this->gateway->fetchTopics($site, $categoryId)
            : RemoteFetchResult::success(collect());

        $remoteReachable = $categories->reachable && $authors->reachable;

        return [
            'types' => ArticleType::cases(),
            'layouts' => ArticleLayout::cases(),
            'statuses' => ArticleStatus::cases(),
            'siteCategories' => $categories->items,
            'siteTopics' => $topics->items,
            'siteAuthors' => $authors->items,
            'editorContent' => $this->editorContentForForm($request, $site, $article),
            'canCreateTaxonomy' => $request->user()?->can('create', [SiteCategory::class, $site]) ?? false,
            'remoteReachable' => $remoteReachable,
            'remoteMessage' => $categories->message ?? $authors->message ?? $topics->message,
        ];
    }

    private function editorContentForForm(Request $request, Site $site, ?RemoteRecord $article): ?string
    {
        $formatter = app(ArticleContentFormatter::class);
        $oldContent = old('content');

        if (is_string($oldContent) && $oldContent !== '') {
            return $formatter->normalize(
                $this->mediaUrlResolver->resolveInHtml($oldContent, $site),
            );
        }

        if ($article instanceof RemoteRecord) {
            return $formatter->normalize($article->string('content') ?? '');
        }

        return null;
    }

    private function findArticle(Site $site, string $articleUuid): ?RemoteRecord
    {
        $remote = $this->gateway->fetchArticle($site, $articleUuid);

        if ($remote->reachable && $remote->items->isNotEmpty()) {
            return $remote->items->first();
        }

        return $this->gateway->mergePendingArticles($site, collect())
            ->first(fn (RemoteRecord $record): bool => $record->key === $articleUuid);
    }

    /**
     * @param  Collection<int, RemoteRecord>  $articles
     * @return Collection<int, RemoteRecord>
     */
    private function filterArticlesForUser(User $user, Collection $articles): Collection
    {
        if ($user->isAdmin()) {
            return $articles;
        }

        return $articles->filter(function (RemoteRecord $article) use ($user): bool {
            $editorId = $article->int('editor_user_id');

            if ($editorId !== null) {
                return $editorId === $user->id;
            }

            return $article->bool('pending_sync');
        })->values();
    }

    private function authorizeArticle(User $user, Site $site, RemoteRecord $article, string $ability): void
    {
        if (! $this->siteAccess->canAccess($user, $site)) {
            abort(403);
        }

        if ($ability === 'view') {
            if ($user->isAdmin() || $article->bool('pending_sync')) {
                return;
            }

            if ($article->int('editor_user_id') === $user->id) {
                return;
            }

            abort(404);
        }

        if ($ability === 'update' || $ability === 'publish') {
            if (! $this->siteAccess->canWriteArticles($user, $site)) {
                abort(403);
            }

            if ($user->isAdmin() || $article->int('editor_user_id') === $user->id) {
                return;
            }

            abort(404);
        }

        if ($ability === 'delete') {
            abort_unless($user->isAdmin(), 403);

            return;
        }

        abort(403);
    }
}
