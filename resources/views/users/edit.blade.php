<x-layouts.app>
    <div class="mb-8">
        <h1 class="text-3xl font-semibold text-slate-900">Edit user</h1>
        <p class="mt-2 text-slate-600">{{ $user->email }}</p>
    </div>

    <form method="POST" action="{{ route('users.update', $user) }}" class="card max-w-2xl space-y-5">
        @csrf
        @method('PUT')
        @include('users._form', ['user' => $user])
        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Update user</button>
            <a href="{{ route('users.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</x-layouts.app>
