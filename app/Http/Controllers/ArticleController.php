<?php

namespace App\Http\Controllers;

use App\Actions\PublishArticleToSites;
use App\Enums\ArticleLayout;
use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Models\Article;
use App\Models\Author;
use App\Models\Site;
use App\Models\SiteCategory;
use App\Models\User;
use App\Support\ArticleContentFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Article::class);

        $articles = Article::query()
            ->visibleTo($request->user())
            ->with(['user:id,name', 'author:id,name', 'site:id,name', 'siteCategory:id,name'])
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

        $this->publishToSite($article, $publisher);

        return redirect()
            ->route('articles.show', $article)
            ->with('status', 'Article saved.');
    }

    public function show(Request $request, Article $article): View
    {
        $this->authorize('view', $article);

        $article->load([
            'user:id,name',
            'author:id,name,credentials,bio',
            'site:id,name',
            'siteCategory:id,name',
            'publishLogs' => fn ($query) => $query->with('site:id,name')->latest()->orderByDesc('id'),
        ]);

        return view('articles.show', [
            'article' => $article,
            'previewContent' => app(ArticleContentFormatter::class)->normalize($article->content),
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

        $this->publishToSite($article->fresh(['author:id,name,credentials,bio', 'site:id,name', 'siteCategory:id,name', 'user:id,name']), $publisher);

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
        $selectedSiteId = (int) old('site_id', $article?->site_id);

        return [
            'types' => ArticleType::cases(),
            'layouts' => ArticleLayout::cases(),
            'statuses' => ArticleStatus::cases(),
            'sites' => Site::query()->active()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'siteCategories' => $selectedSiteId > 0
                ? SiteCategory::query()->where('site_id', $selectedSiteId)->orderBy('name')->orderBy('id')->get(['id', 'name'])
                : collect(),
            'siteAuthors' => $selectedSiteId > 0
                ? Author::query()->where('site_id', $selectedSiteId)->orderBy('name')->orderBy('id')->get(['id', 'name', 'credentials'])
                : collect(),
            'editorContent' => $this->editorContentForForm($request, $article),
        ];
    }

    private function editorContentForForm(Request $request, ?Article $article): ?string
    {
        $formatter = app(ArticleContentFormatter::class);
        $oldContent = old('content');

        if (is_string($oldContent) && $oldContent !== '') {
            return $formatter->normalize($oldContent);
        }

        if ($article instanceof Article) {
            return $formatter->normalize($article->content);
        }

        return null;
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

        $type = ArticleType::from($request->string('type')->toString());
        $featuredImageUrl = $request->string('featured_image_url')->toString() ?: $article?->featured_image_url;

        if ($request->file('featured_image') instanceof UploadedFile) {
            $path = $request->file('featured_image')->store('article-images', 'public');
            $featuredImageUrl = asset('storage/'.$path);
        }

        $layout = $type === ArticleType::Faq
            ? $request->string('layout')->toString()
            : ArticleLayout::Default->value;

        return [
            'type' => $type->value,
            'title' => $request->string('title')->toString(),
            'excerpt' => $request->string('excerpt')->toString() ?: null,
            'keywords' => $request->string('keywords')->toString() ?: null,
            'site_id' => $request->integer('site_id'),
            'site_category_id' => $request->integer('site_category_id'),
            'author_id' => $request->integer('author_id'),
            'featured_image_url' => $featuredImageUrl,
            'content' => app(ArticleContentFormatter::class)->normalize($request->string('content')->toString()),
            'layout' => $layout,
            'status' => $status,
            'published_at' => $publishedAt,
            'reading_time_minutes' => null,
        ];
    }

    private function publishToSite(Article $article, PublishArticleToSites $publisher): void
    {
        if ($article->status !== ArticleStatus::Published || $article->site_id === null) {
            return;
        }

        $site = Site::query()
            ->active()
            ->find($article->site_id);

        if ($site === null) {
            return;
        }

        $publisher->handle($article, collect([$site]));
    }
}
