<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Site;
use App\Models\SiteDelegation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->withCount('articles')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return view('users.index', ['users' => $users]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', $this->formData());
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::query()->create($request->safe()->only(['name', 'email', 'password', 'role']));

        $this->syncDelegations($user, $request);

        return redirect()
            ->route('users.index')
            ->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $user->load('siteDelegations');

        return view('users.edit', [
            'user' => $user,
            ...$this->formData(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $attributes = $request->safe()->only(['name', 'email', 'role']);

        if (filled($request->validated('password'))) {
            $attributes['password'] = $request->validated('password');
        }

        $user->update($attributes);
        $this->syncDelegations($user->fresh(), $request);

        return redirect()
            ->route('users.index')
            ->with('status', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('status', 'User deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'sites' => Site::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
        ];
    }

    private function syncDelegations(User $user, Request $request): void
    {
        $user->siteDelegations()->delete();

        if ($user->role === Role::Admin) {
            return;
        }

        $validSiteIds = Site::query()->pluck('id')->all();

        foreach ($request->input('delegations', []) as $siteId => $delegation) {
            $siteId = (int) $siteId;

            if (! in_array($siteId, $validSiteIds, true)) {
                continue;
            }

            if (! $request->boolean("delegations.{$siteId}.enabled")) {
                continue;
            }

            SiteDelegation::query()->create([
                'user_id' => $user->id,
                'site_id' => $siteId,
                'can_write_articles' => $request->boolean("delegations.{$siteId}.can_write_articles"),
                'can_manage_authors' => $request->boolean("delegations.{$siteId}.can_manage_authors"),
                'can_manage_categories' => $request->boolean("delegations.{$siteId}.can_manage_categories"),
            ]);
        }
    }
}
