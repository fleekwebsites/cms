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
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-nav-link>
                        <x-nav-link :href="route('articles.index')" :active="request()->routeIs('articles.*')">Articles</x-nav-link>
                        @can('viewAny', App\Models\Site::class)
                            @if(auth()->user()->isAdmin())
                                <x-nav-link :href="route('sites.index')" :active="request()->routeIs('sites.*')">Sites</x-nav-link>
                                <x-nav-link :href="route('authors.index')" :active="request()->routeIs('authors.*')">Authors</x-nav-link>
                            @endif
                        @endcan
                        @can('viewAny', App\Models\User::class)
                            <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">Users</x-nav-link>
                        @endcan
                        <x-nav-link :href="route('documentation.index')" :active="request()->routeIs('documentation.*')">Documentation</x-nav-link>
                    </nav>
                </div>

                <div class="flex items-center gap-3">
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
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</body>
</html>
