@php
    $user ??= null;
@endphp

<div>
    <label for="name" class="label">Name</label>
    <input id="name" name="name" type="text" value="{{ old('name', $user?->name) }}" required class="input">
</div>

<div>
    <label for="email" class="label">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" required class="input">
</div>

<div>
    <label for="role" class="label">Role</label>
    <select id="role" name="role" class="input" required>
        @foreach (App\Enums\Role::cases() as $role)
            <option value="{{ $role->value }}" @selected(old('role', $user?->role?->value) === $role->value)>
                {{ $role->label() }}
            </option>
        @endforeach
    </select>
</div>

<div>
    <label for="password" class="label">Password</label>
    <input id="password" name="password" type="password" @if(! $user) required @endif class="input">
</div>

<div>
    <label for="password_confirmation" class="label">Confirm password</label>
    <input id="password_confirmation" name="password_confirmation" type="password" @if(! $user) required @endif class="input">
</div>

@if (($user?->isAdmin() ?? false) === false || old('role', $user?->role?->value) === App\Enums\Role::Writer->value)
    <fieldset class="space-y-3">
        <legend class="label">Site delegations</legend>
        <p class="text-sm text-slate-500">Writers must be assigned to a site before they can open its workspace.</p>
        @forelse ($sites as $site)
            @php
                $delegation = $user?->siteDelegations?->firstWhere('site_id', $site->id);
            @endphp
            <div class="rounded-xl border border-slate-200 p-4 space-y-3">
                <label class="flex items-center gap-2 font-medium text-slate-900">
                    <input type="checkbox" name="delegations[{{ $site->id }}][enabled]" value="1" @checked(old("delegations.{$site->id}.enabled", $delegation !== null))>
                    {{ $site->name }}
                </label>
                <div class="flex flex-wrap gap-4 text-sm text-slate-600">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="delegations[{{ $site->id }}][can_write_articles]" value="1" @checked(old("delegations.{$site->id}.can_write_articles", $delegation?->can_write_articles ?? true))>
                        Write articles
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="delegations[{{ $site->id }}][can_manage_authors]" value="1" @checked(old("delegations.{$site->id}.can_manage_authors", $delegation?->can_manage_authors ?? false))>
                        Manage authors
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="delegations[{{ $site->id }}][can_manage_categories]" value="1" @checked(old("delegations.{$site->id}.can_manage_categories", $delegation?->can_manage_categories ?? false))>
                        Manage categories
                    </label>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">Add a connected site first, then assign writers to it.</p>
        @endforelse
    </fieldset>
@endif
