@php
    $author ??= null;
    $resolvedProfilePhotoUrl ??= null;
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
    <label for="years_of_experience" class="label">Years of experience</label>
    <input
        id="years_of_experience"
        name="years_of_experience"
        type="number"
        min="0"
        max="80"
        value="{{ old('years_of_experience', $author?->int('years_of_experience')) }}"
        class="input max-w-xs"
        placeholder="12"
    >
</div>

<div>
    <label for="profile_photo" class="label">Profile photo</label>
    @if ($resolvedProfilePhotoUrl)
        <img src="{{ $resolvedProfilePhotoUrl }}" alt="Current profile photo" class="mb-3 h-24 w-24 rounded-full border border-slate-200 object-cover">
    @endif
    <input id="profile_photo" name="profile_photo" type="file" accept="image/*" class="input">
    <p class="mt-1 text-xs text-slate-500">JPEG, PNG, GIF, or WebP up to 5 MB.</p>
</div>

<div>
    <label for="bio" class="label">Bio</label>
    <textarea id="bio" name="bio" rows="5" maxlength="2000" class="input">{{ old('bio', $author?->string('bio')) }}</textarea>
</div>
