<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'CMS') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="min-h-screen">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-6">
                    <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-slate-900">
                        {{ config('app.name', 'CMS') }}
                    </a>
                    <nav class="hidden items-center gap-1 md:flex">
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Overview</x-nav-link>
                        @if ($currentSite)
                            <x-nav-link :href="route('sites.articles.index', $currentSite)" :active="request()->routeIs('sites.articles.*')">Articles</x-nav-link>
                            <x-nav-link :href="route('sites.authors.index', $currentSite)" :active="request()->routeIs('sites.authors.*')">Authors</x-nav-link>
                            <x-nav-link :href="route('sites.categories.index', $currentSite)" :active="request()->routeIs('sites.categories.*')">Categories</x-nav-link>
                            @can('view', $currentSite)
                                <x-nav-link :href="route('sites.show', $currentSite)" :active="request()->routeIs('sites.show') || request()->routeIs('sites.edit')">Connection</x-nav-link>
                            @endcan
                        @endif
                        @can('viewAny', App\Models\User::class)
                            <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">Users</x-nav-link>
                        @endcan
                        @if (auth()->user()->isAdmin())
                            <x-nav-link :href="route('sites.index')" :active="request()->routeIs('sites.index') || request()->routeIs('sites.create')">Connections</x-nav-link>
                        @endif
                        <x-nav-link :href="route('documentation.index')" :active="request()->routeIs('documentation.*')">Documentation</x-nav-link>
                    </nav>
                </div>

                <div class="flex items-center gap-3">
                    @if ($accessibleSites->isNotEmpty())
                        <form method="GET" action="{{ url()->current() }}" class="hidden sm:block" onsubmit="return false;">
                            <label for="site-switcher" class="sr-only">Current site</label>
                            <select
                                id="site-switcher"
                                class="input w-auto max-w-56"
                                onchange="if (this.value) { window.location.href = this.value; }"
                            >
                                <option value="{{ route('dashboard') }}" @selected(! $currentSite)>Choose a site</option>
                                @foreach ($accessibleSites as $accessibleSite)
                                    <option value="{{ route('sites.articles.index', $accessibleSite) }}" @selected($currentSite?->is($accessibleSite))>
                                        {{ $accessibleSite->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                    <div class="text-right text-sm">
                        <p class="font-medium text-slate-900">{{ auth()->user()->name }}</p>
                        <p class="text-slate-500">{{ auth()->user()->role->label() }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn-secondary">Log out</button>
                    </form>
                </div>
            </div>
            @if ($currentSite)
                <div class="border-t border-indigo-100 bg-indigo-50">
                    <div class="mx-auto max-w-7xl px-4 py-2 text-sm text-indigo-900 sm:px-6 lg:px-8">
                        Working in <span class="font-semibold">{{ $currentSite->name }}</span>
                    </div>
                </div>
            @endif
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</body>
</html>
