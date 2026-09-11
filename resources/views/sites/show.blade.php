<x-layouts.app>
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-slate-900">{{ $site->name }}</h1>
            <p class="mt-2 text-slate-600">{{ $site->api_endpoint }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            @can('create', [App\Models\Article::class, $site])
                <a href="{{ route('sites.articles.create', $site) }}" class="btn-primary">Write article</a>
            @endcan
            <a href="{{ route('sites.articles.index', $site) }}" class="btn-secondary">Open workspace</a>
            @can('update', $site)
                <form method="POST" action="{{ route('sites.status.update', $site) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn-secondary">
                        {{ $site->is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </form>
                <a href="{{ route('sites.edit', $site) }}" class="btn-secondary">Edit</a>
                <form method="POST" action="{{ route('sites.destroy', $site) }}" onsubmit="return confirm('Delete this site?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger">Delete</button>
                </form>
            @endcan
        </div>
    </div>

    @unless ($remoteReachable)
        <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <p class="font-medium">Remote site unreachable</p>
            <p class="mt-1">{{ $remoteMessage }}</p>
        </div>
    @endunless

    <div class="grid gap-6 lg:grid-cols-2">
        @can('update', $site)
            <section class="card">
                <h2 class="mb-4 text-lg font-semibold text-slate-900">API key</h2>
                <p class="mb-3 text-sm text-slate-600">Send this key in the <code class="rounded bg-slate-100 px-1 py-0.5">X-API-Key</code> header on every remote request.</p>
                <div class="flex items-start gap-2">
                    <div id="api-key-value" class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 p-4 font-mono text-sm break-all">{{ $site->api_key }}</div>
                    <button type="button" class="btn-secondary shrink-0 px-3" title="Copy API key" aria-label="Copy API key" data-copy-target="api-key-value">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="pointer-events-none h-5 w-5" aria-hidden="true">
                            <rect x="9" y="9" width="13" height="13" rx="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                    </button>
                    <form method="POST" action="{{ route('sites.api-key.update', $site) }}" onsubmit="return confirm('Regenerate this API key? The receiving site must be updated.')">
                        @csrf
                        <button type="submit" class="btn-secondary shrink-0 px-3" title="Regenerate API key" aria-label="Regenerate API key">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="pointer-events-none h-5 w-5" aria-hidden="true">
                                <path d="M21 12a9 9 0 1 1-2.6-6.3"></path>
                                <polyline points="21 3 21 9 15 9"></polyline>
                            </svg>
                        </button>
                    </form>
                </div>
            </section>
        @endcan

        <section class="card">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Categories</h2>
            <div class="space-y-2">
                @forelse ($categories as $category)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2">
                        <span class="text-sm font-medium text-slate-900">{{ $category->string('name') }}</span>
                        @can('update', [App\Models\SiteCategory::class, $site])
                            <a href="{{ route('sites.categories.index', $site) }}" class="text-sm text-indigo-600 hover:text-indigo-500">Edit</a>
                        @endcan
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No categories on the remote site yet.</p>
                @endforelse
            </div>
            <a href="{{ route('sites.categories.index', $site) }}" class="mt-4 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500">Manage categories</a>
        </section>
    </div>

    <section class="card mt-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Authors</h2>
            @can('create', [App\Models\Author::class, $site])
                <a href="{{ route('sites.authors.create', $site) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Add author</a>
            @endcan
        </div>
        <div class="space-y-2">
            @forelse ($authors as $author)
                <div class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2">
                    <div>
                        <p class="text-sm font-medium text-slate-900">{{ $author->displayLine() }}</p>
                    </div>
                    @can('update', [App\Models\Author::class, $site])
                        <a href="{{ route('sites.authors.edit', [$site, $author->int('id')]) }}" class="text-sm text-indigo-600 hover:text-indigo-500">Edit</a>
                    @endcan
                </div>
            @empty
                <p class="text-sm text-slate-500">No authors on the remote site yet.</p>
            @endforelse
        </div>
    </section>

    @if ($pendingWrites->isNotEmpty())
        <section class="card mt-6">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Waiting to sync</h2>
            <div class="space-y-2">
                @foreach ($pendingWrites as $write)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        <p class="font-medium">{{ $write->displayTitle() }}</p>
                        <p class="mt-1 text-xs">{{ $write->resource->label() }} · {{ $write->created_at->diffForHumans() }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
