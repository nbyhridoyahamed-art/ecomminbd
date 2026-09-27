<?php

namespace App\Policies;

use App\Models\OrderReturn;
use App\Models\User;

class OrderReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('returns.view');
    }

    public function view(User $user, OrderReturn $orderReturn): bool
    {
        return $user->can('returns.view');
    }

    public function create(User $user): bool
    {
        return $user->can('returns.create');
    }

    public function update(User $user, OrderReturn $orderReturn): bool
    {
        return $user->can('returns.update');
    }
}
