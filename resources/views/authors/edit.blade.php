<x-layouts.app>
    <div class="mb-8">
        <h1 class="text-3xl font-semibold text-slate-900">Edit author</h1>
        <p class="mt-2 text-slate-600">{{ $site->name }}</p>
    </div>

    <form method="POST" action="{{ route('sites.authors.update', [$site, $author]) }}" class="card max-w-2xl space-y-5">
        @csrf
        @method('PUT')
        @include('authors._form', ['author' => $author])
        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save author</button>
            <a href="{{ route('sites.authors.index', $site) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>

    <form method="POST" action="{{ route('sites.authors.destroy', [$site, $author]) }}" class="mt-6" onsubmit="return confirm('Delete this author?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn-danger">Delete author</button>
    </form>
</x-layouts.app>
