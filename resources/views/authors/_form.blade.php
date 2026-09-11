@php
    $author ??= null;
@endphp

<div>
    <p class="label">Site</p>
    <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-900">{{ $site->name }}</p>
    <p class="mt-1 text-xs text-slate-500">Authors are stored on the remote site, not in the CMS.</p>
</div>

<div>
    <label for="name" class="label">Name</label>
    <input id="name" name="name" type="text" value="{{ old('name', $author?->string('name')) }}" maxlength="255" required class="input">
</div>

<div>
    <label for="credentials" class="label">Credentials</label>
    <input id="credentials" name="credentials" type="text" value="{{ old('credentials', $author?->string('credentials')) }}" maxlength="255" class="input" placeholder="DNP, FNP-BC">
</div>

<div>
    <label for="bio" class="label">Bio</label>
    <textarea id="bio" name="bio" rows="5" maxlength="2000" class="input">{{ old('bio', $author?->string('bio')) }}</textarea>
</div>
