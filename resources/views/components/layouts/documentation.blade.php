@props([
    'title',
    'slug' => null,
    'pages' => [],
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — API Documentation — {{ config('app.name', 'CMS') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="documentation-body">
    <input type="checkbox" id="documentation-nav-toggle" class="documentation-nav-toggle" aria-hidden="true">

    <div class="documentation-shell">
        <aside class="documentation-sidebar" aria-label="Documentation navigation">
            <div class="documentation-sidebar__header">
                <a href="{{ route('documentation.index') }}" class="documentation-sidebar__brand">
                    <span class="documentation-sidebar__brand-title">{{ config('app.name', 'CMS') }}</span>
                    <span class="documentation-sidebar__brand-subtitle">API documentation</span>
                </a>

                <label for="documentation-nav-toggle" class="documentation-sidebar__close" aria-label="Close navigation">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                    </svg>
                </label>
            </div>

            <nav class="documentation-sidebar__nav">
                <p class="documentation-sidebar__label">Sections</p>

                <ul class="documentation-sidebar__list">
                    @foreach ($pages as $pageSlug => $page)
                        <li>
                            <a
                                href="{{ $pageSlug === 'overview' ? route('documentation.index') : route('documentation.show', $pageSlug) }}"
                                @class([
                                    'documentation-nav-link',
                                    'is-active' => $slug === $pageSlug,
                                ])
                            >
                                <span class="documentation-nav-link__indicator" aria-hidden="true"></span>
                                <span>{{ $page['title'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="documentation-sidebar__footer">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-secondary documentation-sidebar__action">Back to dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn-primary documentation-sidebar__action">Sign in to CMS</a>
                @endauth
            </div>
        </aside>

        <label for="documentation-nav-toggle" class="documentation-overlay" aria-hidden="true"></label>

        <div class="documentation-main">
            <header class="documentation-topbar">
                <div class="documentation-topbar__inner">
                    <div class="documentation-topbar__title-group">
                        <label for="documentation-nav-toggle" class="documentation-topbar__menu" aria-label="Open navigation">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 5.5a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75a.75.75 0 01-.75-.75zm0 5.5a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75a.75.75 0 01-.75-.75z" clip-rule="evenodd" />
                            </svg>
                        </label>

                        <div class="documentation-topbar__titles">
                            <p class="documentation-topbar__eyebrow">Documentation</p>
                            <h1 class="documentation-topbar__title">{{ $title }}</h1>
                        </div>
                    </div>

                    <div class="documentation-topbar__actions">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn-secondary">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="btn-primary">Sign in</a>
                        @endauth
                    </div>
                </div>
            </header>

            <main class="documentation-page">
                <article class="documentation-content">
                    {{ $slot }}
                </article>
            </main>
        </div>
    </div>
</body>
</html>
