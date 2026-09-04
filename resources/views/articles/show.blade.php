<x-layouts.app>
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-indigo-600">{{ $article->type->label() }}</p>
            <h1 class="mt-2 text-3xl font-semibold text-slate-900">{{ $article->title }}</h1>
            <p class="mt-2 text-slate-600">
                {{ $article->status->label() }} · {{ $article->layout->label() }}
                · {{ $article->display_author_name }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            @can('update', $article)
                <a href="{{ route('articles.edit', $article) }}" class="btn-secondary">Edit</a>
            @endcan
            @can('delete', $article)
                <form method="POST" action="{{ route('articles.destroy', $article) }}" onsubmit="return confirm('Delete this article?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger">Delete</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card">
                <h2 class="mb-4 text-lg font-semibold text-slate-900">Preview</h2>
                @if ($article->excerpt)
                    <p class="mb-4 text-slate-600">{{ $article->excerpt }}</p>
                @endif
                <div class="article-content">
                    {!! $article->content !!}
                </div>
            </section>

            <section class="card">
                <h2 class="mb-4 text-lg font-semibold text-slate-900">Publish history</h2>
                <div class="space-y-4">
                    @forelse ($article->publishLogs as $log)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $log->site->name }}</p>
                                    <p class="mt-1 text-sm text-slate-500">
                                        HTTP {{ $log->response_code ?? 'n/a' }} · {{ $log->created_at->format('M j, Y g:i A') }}
                                    </p>
                                </div>
                                <span @class([
                                    'badge',
                                    'bg-emerald-100 text-emerald-700' => $log->status->value === 'success',
                                    'bg-rose-100 text-rose-700' => $log->status->value === 'failed',
                                ])>
                                    {{ $log->status->label() }}
                                </span>
                            </div>

                            @if ($log->error_message)
                                <p class="mt-3 text-sm text-rose-700">{{ $log->error_message }}</p>
                            @endif

                            @if ($log->response_payload)
                                <details class="mt-3">
                                    <summary class="cursor-pointer text-sm font-medium text-slate-700">Response payload</summary>
                                    <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-slate-100">{{ $log->response_payload }}</pre>
                                </details>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">This article has not been published yet.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="space-y-6">
            @can('publish', $article)
                @if ($sites->isNotEmpty())
                    <section class="card">
                        <h2 class="mb-4 text-lg font-semibold text-slate-900">Publish to sites</h2>
                        <form method="POST" action="{{ route('articles.publications.store', $article) }}" class="space-y-4">
                            @csrf
                            <div class="space-y-2">
                                @foreach ($sites as $site)
                                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3">
                                        <input
                                            type="checkbox"
                                            name="site_ids[]"
                                            value="{{ $site->id }}"
                                            class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        >
                                        <span>
                                            <span class="block font-medium text-slate-900">{{ $site->name }}</span>
                                            <span class="block text-xs text-slate-500">{{ $site->api_endpoint }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <button type="submit" class="btn-primary w-full">Publish now</button>
                        </form>
                    </section>
                @endif
            @endcan

            <section class="card text-sm text-slate-600">
                <h2 class="mb-3 text-lg font-semibold text-slate-900">Metadata</h2>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-slate-500">Author</dt>
                        <dd class="font-medium text-slate-900">{{ $article->display_author_name }}</dd>
                    </div>
                    @if ($article->keywords)
                        <div>
                            <dt class="text-slate-500">Keywords</dt>
                            <dd class="font-medium text-slate-900">{{ $article->keywords }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-slate-500">Slug</dt>
                        <dd class="font-medium text-slate-900">{{ $article->slug }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">UUID</dt>
                        <dd class="break-all font-medium text-slate-900">{{ $article->uuid }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Created</dt>
                        <dd class="font-medium text-slate-900">{{ $article->created_at->format('M j, Y g:i A') }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Updated</dt>
                        <dd class="font-medium text-slate-900">{{ $article->updated_at->format('M j, Y g:i A') }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
</x-layouts.app>
