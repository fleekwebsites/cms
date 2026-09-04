<x-layouts.app>
    <div class="mb-8">
        <h1 class="text-3xl font-semibold text-slate-900">Dashboard</h1>
        <p class="mt-2 text-slate-600">Centralized blog and FAQ management for your connected sites.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card">
            <p class="text-sm text-slate-500">Blogs</p>
            <p class="mt-2 text-3xl font-semibold">{{ $blogCount }}</p>
        </div>
        <div class="card">
            <p class="text-sm text-slate-500">FAQs</p>
            <p class="mt-2 text-3xl font-semibold">{{ $faqCount }}</p>
        </div>
        <div class="card">
            <p class="text-sm text-slate-500">Published</p>
            <p class="mt-2 text-3xl font-semibold">{{ $publishedCount }}</p>
        </div>
        <div class="card">
            <p class="text-sm text-slate-500">Drafts</p>
            <p class="mt-2 text-3xl font-semibold">{{ $draftCount }}</p>
        </div>
    </div>

    @if ($siteCount !== null)
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="card">
                <p class="text-sm text-slate-500">Connected sites</p>
                <p class="mt-2 text-3xl font-semibold">{{ $siteCount }}</p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">Users</p>
                <p class="mt-2 text-3xl font-semibold">{{ $userCount }}</p>
            </div>
        </div>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="card">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Recent articles</h2>
                <a href="{{ route('articles.create') }}" class="btn-primary">New article</a>
            </div>

            <div class="space-y-4">
                @forelse ($recentArticles as $article)
                    <a href="{{ route('articles.show', $article) }}" class="block rounded-xl border border-slate-200 p-4 hover:border-indigo-200 hover:bg-indigo-50/40">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900">{{ $article->title }}</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $article->type->label() }} · {{ $article->status->label() }}
                                    @if(auth()->user()->isAdmin())
                                        · {{ $article->display_author_name }}
                                    @endif
                                </p>
                            </div>
                            <span class="badge bg-slate-100 text-slate-700">{{ $article->layout->label() }}</span>
                        </div>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">No articles yet.</p>
                @endforelse
            </div>
        </section>

        <section class="card">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Recent publish activity</h2>
            <div class="space-y-4">
                @forelse ($recentLogs as $log)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900">{{ $log->article->title }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $log->site->name }}</p>
                            </div>
                            <span @class([
                                'badge',
                                'bg-emerald-100 text-emerald-700' => $log->status->value === 'success',
                                'bg-rose-100 text-rose-700' => $log->status->value === 'failed',
                            ])>
                                {{ $log->status->label() }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">
                            HTTP {{ $log->response_code ?? 'n/a' }} · {{ $log->created_at->diffForHumans() }}
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No publish attempts yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
