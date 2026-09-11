<x-layouts.app>
    <div class="mb-8">
        <h1 class="text-3xl font-semibold text-slate-900">Categories</h1>
        <p class="mt-2 text-slate-600">Managed on {{ $site->name }}. The CMS sends changes to the remote site.</p>
    </div>

    @unless ($remoteReachable)
        <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <p class="font-medium">Remote site unreachable</p>
            <p class="mt-1">{{ $remoteMessage }}</p>
        </div>
    @endunless

    @can('create', [App\Models\SiteCategory::class, $site])
        <form method="POST" action="{{ route('sites.categories.store', $site) }}" class="card mb-6 flex flex-wrap gap-3">
            @csrf
            <input type="text" name="name" maxlength="120" required class="input max-w-sm" placeholder="NP Programs">
            <button type="submit" class="btn-primary">Add category</button>
        </form>
    @endcan

    <div class="card space-y-3">
        @forelse ($categories as $category)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3">
                @can('update', [App\Models\SiteCategory::class, $site])
                    <form method="POST" action="{{ route('sites.categories.update', [$site, $category->int('id')]) }}" class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                        @csrf
                        @method('PUT')
                        <input type="text" name="name" value="{{ old('name', $category->string('name')) }}" maxlength="120" required class="input max-w-sm">
                        <button type="submit" class="btn-secondary">Save</button>
                    </form>
                @else
                    <span class="font-medium text-slate-900">{{ $category->string('name') }}</span>
                @endcan
                @can('delete', [App\Models\SiteCategory::class, $site])
                    <form method="POST" action="{{ route('sites.categories.destroy', [$site, $category->int('id')]) }}" onsubmit="return confirm('Remove this category from the remote site?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-rose-600 hover:text-rose-500">Remove</button>
                    </form>
                @endcan
            </div>
        @empty
            <p class="text-sm text-slate-500">No categories on the remote site yet.</p>
        @endforelse
    </div>
</x-layouts.app>
