<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(Role::class)],
            'delegations' => ['nullable', 'array'],
            'delegations.*.enabled' => ['sometimes', 'boolean'],
            'delegations.*.can_write_articles' => ['sometimes', 'boolean'],
            'delegations.*.can_manage_authors' => ['sometimes', 'boolean'],
            'delegations.*.can_manage_categories' => ['sometimes', 'boolean'],
        ];
    }
}
