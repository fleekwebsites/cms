<x-layouts.app>
    <div class="mb-8">
        <h1 class="text-3xl font-semibold text-slate-900">Edit article</h1>
        <p class="mt-2 text-slate-600">{{ $article->title }}</p>
    </div>

    <form method="POST" action="{{ route('articles.update', $article) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')
        @include('articles._form', ['article' => $article])
        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Update article</button>
            <a href="{{ route('articles.show', $article) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</x-layouts.app>
