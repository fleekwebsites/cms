<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in - {{ config('app.name', 'CMS') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 px-4">
    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-semibold text-slate-900">{{ config('app.name', 'CMS') }}</h1>
            <p class="mt-2 text-sm text-slate-500">Sign in to manage blogs and FAQs.</p>
        </div>

        <x-flash />

        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="input">
            </div>

            <div>
                <label for="password" class="label">Password</label>
                <input id="password" name="password" type="password" required class="input">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                Remember me
            </label>

            <button type="submit" class="btn-primary w-full">Sign in</button>
        </form>
    </div>
</body>
</html>
