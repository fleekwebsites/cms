@props(['href', 'active' => false])

<a
    href="{{ $href }}"
    @class([
        'rounded-lg px-3 py-2 text-sm font-medium transition',
        'bg-indigo-50 text-indigo-700' => $active,
        'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $active,
    ])
>
    {{ $slot }}
</a>
