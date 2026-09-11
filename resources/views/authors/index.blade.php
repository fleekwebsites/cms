<x-layouts.app>
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-slate-900">Authors</h1>
            <p class="mt-2 text-slate-600">Bylines loaded from {{ $site->name }}.</p>
        </div>
        @can('create', [App\Models\Author::class, $site])
            <a href="{{ route('sites.authors.create', $site) }}" class="btn-primary">Add author</a>
        @endcan
    </div>

    @unless ($remoteReachable)
        <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <p class="font-medium">Remote site unreachable</p>
            <p class="mt-1">{{ $remoteMessage }}</p>
        </div>
    @endunless

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Author</th>
                        <th class="px-6 py-3 font-medium">Credentials</th>
                        <th class="px-6 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($authors as $author)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-900">{{ $author->string('name') }}</p>
                                @if ($author->string('bio'))
                                    <p class="mt-1 text-xs text-slate-500">{{ Str::limit($author->string('bio'), 100) }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $author->string('credentials') ?? '—' }}</td>
                            <td class="px-6 py-4 text-right">
                                @can('update', [App\Models\Author::class, $site])
                                    <a href="{{ route('sites.authors.edit', [$site, $author->int('id')]) }}" class="text-indigo-600 hover:text-indigo-500">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-slate-500">No authors on the remote site yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $authors->links() }}</div>
</x-layouts.app>
