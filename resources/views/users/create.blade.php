<x-layouts.app>
    <div class="mb-8">
        <h1 class="text-3xl font-semibold text-slate-900">Add user</h1>
    </div>

    <form method="POST" action="{{ route('users.store') }}" class="card max-w-2xl space-y-5">
        @csrf
        @include('users._form')
        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Create user</button>
            <a href="{{ route('users.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</x-layouts.app>
