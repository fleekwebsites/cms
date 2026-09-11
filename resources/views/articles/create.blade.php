<x-layouts.app>
    <div class="mb-8">
        <h1 class="text-3xl font-semibold text-slate-900">Create article</h1>
        <p class="mt-2 text-slate-600">Write content for {{ $site->name }}.</p>
    </div>

    <form method="POST" action="{{ route('sites.articles.store', $site) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @include('articles._form')
        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save article</button>
            <a href="{{ route('sites.articles.index', $site) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</x-layouts.app>
