<x-layouts.app>
    <div class="mb-8">
        <h1 class="text-3xl font-semibold text-slate-900">CMS overview</h1>
        <p class="mt-2 text-slate-600">Sites, users, and temporary sync queues. Content lives on remote sites.</p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Connected sites</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $siteCount }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Pending sync</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $pendingWriteCount }}</p>
        </div>
        @if ($userCount !== null)
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Users</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $userCount }}</p>
            </div>
        @endif
    </div>

    <h2 class="mt-8 text-lg font-semibold text-slate-900">Sites</h2>
    <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @forelse ($sites as $site)
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <a href="{{ route('sites.show', $site) }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ $site->name }}</a>
                <p class="mt-1 text-xs text-slate-500">{{ $site->is_active ? 'Active' : 'Inactive' }}</p>
                <a href="{{ route('sites.articles.index', $site) }}" class="mt-2 inline-block text-xs font-medium text-slate-700 hover:text-indigo-600">Open workspace</a>
            </div>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500 sm:col-span-2">
                No sites are assigned to you yet. Ask an admin to delegate a site.
            </div>
        @endforelse
    </div>

    <section class="card mt-8">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Recent sync queue</h2>
        <div class="space-y-3">
            @forelse ($recentPendingWrites as $write)
                <div class="rounded-lg border border-slate-200 px-3 py-2">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-medium text-slate-900">{{ $write->displayTitle() }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $write->site->name }} · {{ $write->resource->label() }}</p>
                        </div>
                        <span @class([
                            'badge',
                            'bg-amber-100 text-amber-800' => $write->status->value === 'pending',
                            'bg-emerald-100 text-emerald-700' => $write->status->value === 'sent',
                        ])>
                            {{ $write->status->label() }}
                        </span>
                    </div>
                    @if ($write->error_message)
                        <p class="mt-2 text-xs text-rose-700">{{ $write->error_message }}</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">Nothing is waiting to sync.</p>
            @endforelse
        </div>
    </section>
</x-layouts.app>
