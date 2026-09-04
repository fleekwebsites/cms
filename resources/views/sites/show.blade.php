<x-layouts.app>
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-slate-900">{{ $site->name }}</h1>
            <p class="mt-2 text-slate-600">{{ $site->api_endpoint }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
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
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">API key</h2>
            <p class="mb-3 text-sm text-slate-600">Send this key in the <code class="rounded bg-slate-100 px-1 py-0.5">X-API-Key</code> header when receiving content.</p>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 font-mono text-sm break-all">{{ $site->api_key }}</div>
            <form method="POST" action="{{ route('sites.api-key.update', $site) }}" class="mt-4">
                @csrf
                <button type="submit" class="btn-secondary">Regenerate API key</button>
            </form>
        </section>

        <section class="card">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Recent deliveries</h2>
            <div class="space-y-4">
                @forelse ($site->publishLogs as $log)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="font-medium text-slate-900">{{ $log->article->title }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            HTTP {{ $log->response_code ?? 'n/a' }} · {{ $log->status->label() }}
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No deliveries yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
