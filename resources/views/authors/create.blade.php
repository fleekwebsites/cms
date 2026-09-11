<x-layouts.app>
    <div class="mb-8">
        <h1 class="text-3xl font-semibold text-slate-900">Add author</h1>
        <p class="mt-2 text-slate-600">{{ $site->name }}</p>
    </div>

    <form method="POST" action="{{ route('sites.authors.store', $site) }}" class="card max-w-2xl space-y-5">
        @csrf
        @include('authors._form')
        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Create author</button>
            <a href="{{ route('sites.authors.index', $site) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</x-layouts.app>
