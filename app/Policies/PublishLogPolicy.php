<?php

namespace App\Policies;

use App\Models\PublishLog;
use App\Models\User;

class PublishLogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PublishLog $publishLog): bool
    {
        return $user->can('view', $publishLog->article);
    }
}
