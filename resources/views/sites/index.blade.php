<x-layouts.app>
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-slate-900">Connections</h1>
            <p class="mt-2 text-slate-600">API keys, categories, and authors for each connected site.</p>
        </div>
        <a href="{{ route('sites.create') }}" class="btn-primary">Add site</a>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Endpoint</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Deliveries</th>
                        <th class="px-6 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($sites as $site)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">
                                <a href="{{ route('sites.show', $site) }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                                    {{ $site->name }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $site->api_endpoint }}</td>
                            <td class="px-6 py-4">
                                <span @class([
                                    'badge',
                                    'bg-emerald-100 text-emerald-700' => $site->is_active,
                                    'bg-slate-100 text-slate-700' => ! $site->is_active,
                                ])>
                                    {{ $site->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">{{ $site->publish_logs_count }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('sites.status.update', $site) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn-secondary">
                                            {{ $site->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('sites.destroy', $site) }}" onsubmit="return confirm('Delete this site? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-slate-500">No sites connected yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $sites->links() }}
    </div>
</x-layouts.app>
