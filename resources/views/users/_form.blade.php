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
