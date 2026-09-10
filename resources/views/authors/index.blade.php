<x-layouts.app>
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-slate-900">Authors</h1>
            <p class="mt-2 text-slate-600">Manage bylines used on published articles. Writers select an author when drafting.</p>
        </div>
        <a href="{{ route('authors.create') }}" class="btn-primary">Add author</a>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Author</th>
                        <th class="px-6 py-3 font-medium">Site</th>
                        <th class="px-6 py-3 font-medium">Credentials</th>
                        <th class="px-6 py-3 font-medium">Articles</th>
                        <th class="px-6 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($authors as $author)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-900">{{ $author->name }}</p>
                                @if ($author->bio)
                                    <p class="mt-1 text-xs text-slate-500">{{ Str::limit($author->bio, 100) }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $author->site->name }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $author->credentials ?? '—' }}</td>
                            <td class="px-6 py-4">{{ $author->articles_count }}</td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('authors.edit', $author) }}" class="text-indigo-600 hover:text-indigo-500">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-slate-500">No authors yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $authors->links() }}</div>
</x-layouts.app>
