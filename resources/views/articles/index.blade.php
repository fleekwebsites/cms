<x-layouts.app>
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-slate-900">Articles</h1>
            <p class="mt-2 text-slate-600">Create blogs and FAQs, then publish them to connected sites.</p>
        </div>
        <a href="{{ route('articles.create') }}" class="btn-primary">New article</a>
    </div>

    <form method="GET" class="mb-6 flex flex-wrap gap-3">
        <select name="type" class="input w-auto">
            <option value="">All types</option>
            @foreach (App\Enums\ArticleType::cases() as $type)
                <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        <select name="status" class="input w-auto">
            <option value="">All statuses</option>
            @foreach (App\Enums\ArticleStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-secondary">Filter</button>
    </form>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Title</th>
                        <th class="px-6 py-3 font-medium">Type</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Layout</th>
                        @if(auth()->user()->isAdmin())
                            <th class="px-6 py-3 font-medium">Author</th>
                        @endif
                        <th class="px-6 py-3 font-medium">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($articles as $article)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">
                                <a href="{{ route('articles.show', $article) }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                                    {{ $article->title }}
                                </a>
                            </td>
                            <td class="px-6 py-4">{{ $article->type->label() }}</td>
                            <td class="px-6 py-4">{{ $article->status->label() }}</td>
                            <td class="px-6 py-4">{{ $article->layout->label() }}</td>
                            @if(auth()->user()->isAdmin())
                                <td class="px-6 py-4">{{ $article->display_author_name }}</td>
                            @endif
                            <td class="px-6 py-4 text-slate-500">{{ $article->updated_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->isAdmin() ? 6 : 5 }}" class="px-6 py-8 text-center text-slate-500">
                                No articles found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $articles->links() }}
    </div>
</x-layouts.app>
