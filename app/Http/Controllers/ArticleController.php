<?php

namespace App\Http\Controllers;

use App\Actions\PublishArticleToSites;
use App\Enums\ArticleLayout;
use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Models\Article;
use App\Models\AuthorName;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Article::class);

        $articles = Article::query()
            ->visibleTo($request->user())
            ->with(['user:id,name', 'authorName:id,name'])
            ->when(
                $request->filled('type'),
                fn ($query) => $query->where('type', $request->string('type')->toString()),
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString()),
            )
            ->latest()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('articles.index', [
            'articles' => $articles,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Article::class);

        return view('articles.create', $this->formData($request));
    }

    public function store(StoreArticleRequest $request, PublishArticleToSites $publisher): RedirectResponse
    {
        $article = Article::query()->create([
            ...$this->articleAttributes($request, $request->user()),
            'user_id' => $request->user()->id,
            'uuid' => (string) Str::uuid(),
            'slug' => Article::uniqueSlug($request->string('title')->toString()),
        ]);

        $this->publishSelectedSites($request, $article, $publisher);

        return redirect()
            ->route('articles.show', $article)
            ->with('status', 'Article saved.');
    }

    public function show(Request $request, Article $article): View
    {
        $this->authorize('view', $article);

        $article->load([
            'user:id,name',
            'authorName:id,name',
            'publishLogs' => fn ($query) => $query->with('site:id,name')->latest()->orderByDesc('id'),
        ]);

        return view('articles.show', [
            'article' => $article,
            'sites' => $this->publishableSites($request),
        ]);
    }

    public function edit(Request $request, Article $article): View
    {
        $this->authorize('update', $article);

        return view('articles.edit', [
            'article' => $article,
            ...$this->formData($request, $article),
        ]);
    }

    public function update(UpdateArticleRequest $request, Article $article, PublishArticleToSites $publisher): RedirectResponse
    {
        $article->update([
            ...$this->articleAttributes($request, $article->user, $article),
            'slug' => Article::uniqueSlug($request->string('title')->toString(), $article->id),
        ]);

        $this->publishSelectedSites($request, $article->fresh(['authorName:id,name', 'user:id,name']), $publisher);

        return redirect()
            ->route('articles.show', $article)
            ->with('status', 'Article updated.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        $article->delete();

        return redirect()
            ->route('articles.index')
            ->with('status', 'Article deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, ?Article $article = null): array
    {
        $owner = $article?->user ?? $request->user();

        return [
            'types' => ArticleType::cases(),
            'layouts' => ArticleLayout::cases(),
            'statuses' => ArticleStatus::cases(),
            'sites' => $this->publishableSites($request),
            'authorNames' => $this->authorNamesFor($owner),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function articleAttributes(
        StoreArticleRequest|UpdateArticleRequest $request,
        User $owner,
        ?Article $article = null,
    ): array {
        $status = ArticleStatus::from($request->string('status')->toString());
        $publishedAt = $article?->published_at;

        if ($status === ArticleStatus::Published && $publishedAt === null) {
            $publishedAt = now();
        }

        if ($status === ArticleStatus::Draft) {
            $publishedAt = null;
        }

        $featuredImageUrl = $request->string('featured_image_url')->toString() ?: $article?->featured_image_url;

        if ($request->file('featured_image') instanceof UploadedFile) {
            $path = $request->file('featured_image')->store('article-images', 'public');
            $featuredImageUrl = asset('storage/'.$path);
        }

        return [
            'type' => $request->string('type')->toString(),
            'title' => $request->string('title')->toString(),
            'excerpt' => $request->string('excerpt')->toString() ?: null,
            'keywords' => $request->string('keywords')->toString() ?: null,
            'author_name_id' => $this->resolveAuthorNameId($request, $owner),
            'featured_image_url' => $featuredImageUrl,
            'content' => $request->string('content')->toString(),
            'layout' => $request->string('layout')->toString(),
            'status' => $status,
            'published_at' => $publishedAt,
        ];
    }

    private function resolveAuthorNameId(StoreArticleRequest|UpdateArticleRequest $request, User $owner): ?int
    {
        $newName = trim($request->string('new_author_name')->toString());

        if ($newName !== '') {
            return AuthorName::query()->firstOrCreate([
                'user_id' => $owner->id,
                'name' => $newName,
            ])->id;
        }

        if ($request->filled('author_name_id')) {
            return AuthorName::query()
                ->where('user_id', $owner->id)
                ->whereKey($request->integer('author_name_id'))
                ->value('id');
        }

        return null;
    }

    /**
     * @return Collection<int, AuthorName>
     */
    private function authorNamesFor(User $owner)
    {
        return AuthorName::query()
            ->where('user_id', $owner->id)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Site>
     */
    private function publishableSites(Request $request)
    {
        $this->authorize('viewAny', Site::class);

        return Site::query()
            ->active()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'api_endpoint']);
    }

    private function publishSelectedSites(StoreArticleRequest|UpdateArticleRequest $request, Article $article, PublishArticleToSites $publisher): void
    {
        $siteIds = $request->validated('site_ids', []);

        if ($article->status !== ArticleStatus::Published || $siteIds === []) {
            return;
        }

        $sites = Site::query()
            ->active()
            ->whereIn('id', $siteIds)
            ->orderBy('name')
            ->get();

        $publisher->handle($article, $sites);
    }
}
