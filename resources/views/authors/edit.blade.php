<x-layouts.app>
    <div class="mb-8">
        <h1 class="text-3xl font-semibold text-slate-900">Edit author</h1>
    </div>

    <form method="POST" action="{{ route('authors.update', $author) }}" class="card max-w-2xl space-y-5">
        @csrf
        @method('PUT')
        @include('authors._form', ['author' => $author])
        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save author</button>
            <a href="{{ route('authors.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>

    <form method="POST" action="{{ route('authors.destroy', $author) }}" class="mt-6" onsubmit="return confirm('Delete this author?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn-danger">Delete author</button>
    </form>
</x-layouts.app>
