<x-layouts.app>
    <div class="mb-8 flex min-w-0 flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium uppercase tracking-wide text-indigo-600">{{ $article->type()->label() }}</p>
            @if ($categoryName)
                <p class="mt-2 inline-flex rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                    {{ $categoryName }}
                    @if ($topicName)
                        · {{ $topicName }}
                    @endif
                </p>
            @endif
            <h1 class="mt-2 text-3xl font-semibold text-slate-900">{{ $article->string('title') }}</h1>
            <p class="mt-2 max-w-full text-slate-600">
                {{ $article->status()->label() }} · {{ $article->layout()->label() }}
                · {{ $site->name }}
                @if ($authorRecord)
                    · {{ $authorRecord->displayLine() }}
                @endif
                · {{ $article->readingTimeMinutes() }} min read
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            @if (! $pendingSync)
                <a href="{{ route('sites.articles.edit', [$site, $article->key]) }}" class="btn-secondary">Edit</a>
            @endif
            @if(auth()->user()->isAdmin())
                <form method="POST" action="{{ route('sites.articles.destroy', [$site, $article->key]) }}" onsubmit="return confirm('Delete this article from the remote site?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger">Delete</button>
                </form>
            @endif
        </div>
    </div>

    @if ($pendingSync)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-medium">Waiting for remote site</p>
            <p class="mt-1">This article is stored temporarily on the CMS and will be sent to {{ $site->name }} as soon as the site is reachable.</p>
        </div>
    @endif

    <div class="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-6">
            <section class="card min-w-0">
                <h2 class="mb-4 text-lg font-semibold text-slate-900">Preview</h2>
                @if ($article->type() === \App\Enums\ArticleType::Blog && $article->string('featured_image_url'))
                    <img
                        src="{{ $article->string('featured_image_url') }}"
                        alt="{{ $article->string('title') }}"
                        class="mb-6 w-full max-h-96 rounded-xl border border-slate-200 object-cover"
                    >
                @endif
                @if ($article->string('excerpt'))
                    <p class="mb-4 text-slate-600">{{ $article->string('excerpt') }}</p>
                @endif
                <div class="article-content min-w-0 max-w-full">
                    {!! $previewContent !!}
                </div>
                @if ($authorRecord?->string('bio'))
                    <div class="mt-8 border-t border-slate-200 pt-6">
                        <p class="text-sm font-semibold text-slate-900">{{ $authorRecord->displayLine() }}</p>
                        <p class="mt-2 text-sm leading-7 text-slate-600">{{ $authorRecord->string('bio') }}</p>
                    </div>
                @endif
            </section>
        </div>

        <div class="min-w-0 space-y-6">
            @if (! $pendingSync)
                <section class="card">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900">Publishing</h2>

                    @if ($article->status() === \App\Enums\ArticleStatus::Draft)
                        <p class="mb-4 text-sm text-slate-600">This article is still a draft. Mark it as complete when editing is finished, then publish it to {{ $site->name }}.</p>
                        <form method="POST" action="{{ route('sites.articles.completion.store', [$site, $article->key]) }}">
                            @csrf
                            <button type="submit" class="btn-primary w-full">Mark as complete</button>
                        </form>
                    @elseif ($article->status() === \App\Enums\ArticleStatus::Complete)
                        <p class="mb-4 text-sm text-slate-600">Editing is complete. Publish this article to {{ $site->name }} when you are ready.</p>
                        <form method="POST" action="{{ route('sites.articles.publications.store', [$site, $article->key]) }}">
                            @csrf
                            <button type="submit" class="btn-primary w-full">Publish</button>
                        </form>
                    @else
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                            <p class="text-sm font-medium text-emerald-900">Published</p>
                            <p class="mt-1 text-sm text-emerald-800">This article is live on {{ $site->name }}.</p>
                        </div>
                    @endif
                </section>
            @endif

            <section class="card text-sm text-slate-600">
                <h2 class="mb-3 text-lg font-semibold text-slate-900">Metadata</h2>
                <dl class="space-y-3">
                    @if ($authorRecord)
                        <div>
                            <dt class="text-slate-500">Author</dt>
                            <dd class="font-medium text-slate-900">{{ $authorRecord->displayLine() }}</dd>
                        </div>
                    @endif
                    @if ($article->string('keywords'))
                        <div>
                            <dt class="text-slate-500">Keywords</dt>
                            <dd class="font-medium text-slate-900">{{ $article->string('keywords') }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-slate-500">Slug</dt>
                        <dd class="font-medium text-slate-900">{{ $article->string('slug') }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">UUID</dt>
                        <dd class="break-all font-medium text-slate-900">{{ $article->key }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
</x-layouts.app>
