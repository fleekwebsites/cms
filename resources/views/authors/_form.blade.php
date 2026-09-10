@php
    $author ??= null;
@endphp

@if ($author?->site)
    <div>
        <p class="label">Site</p>
        <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-900">{{ $author->site->name }}</p>
        <p class="mt-1 text-xs text-slate-500">Authors stay on the site they were created for.</p>
    </div>
@elseif (isset($sites))
    <div>
        <label for="site_id" class="label">Site</label>
        <select id="site_id" name="site_id" class="input" required>
            <option value="">Select a site</option>
            @foreach ($sites as $site)
                <option value="{{ $site->id }}" @selected((string) old('site_id') === (string) $site->id)>
                    {{ $site->name }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">An author can only write for this one site.</p>
    </div>
@endif

<div>
    <label for="name" class="label">Name</label>
    <input id="name" name="name" type="text" value="{{ old('name', $author?->name) }}" maxlength="255" required class="input">
</div>

<div>
    <label for="credentials" class="label">Credentials</label>
    <input id="credentials" name="credentials" type="text" value="{{ old('credentials', $author?->credentials) }}" maxlength="255" class="input" placeholder="DNP, FNP-BC">
</div>

<div>
    <label for="bio" class="label">Bio</label>
    <textarea id="bio" name="bio" rows="5" maxlength="2000" class="input">{{ old('bio', $author?->bio) }}</textarea>
</div>
