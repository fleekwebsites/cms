<x-layouts.app>
    <div class="mb-8">
        <h1 class="text-3xl font-semibold text-slate-900">Edit site</h1>
        <p class="mt-2 text-slate-600">{{ $site->name }}</p>
    </div>

    <form method="POST" action="{{ route('sites.update', $site) }}" class="card max-w-2xl space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="label">Site name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $site->name) }}" required class="input">
        </div>

        <div>
            <label for="api_endpoint" class="label">API endpoint</label>
            <input id="api_endpoint" name="api_endpoint" type="url" value="{{ old('api_endpoint', $site->api_endpoint) }}" required class="input">
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $site->is_active)) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            Active
        </label>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Update site</button>
            <a href="{{ route('sites.show', $site) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</x-layouts.app>
