<?php

namespace App\Policies;

use App\Models\Courier;
use App\Models\User;

class CourierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('couriers.view');
    }

    public function view(User $user, Courier $courier): bool
    {
        return $user->can('couriers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('couriers.create');
    }

    public function update(User $user, Courier $courier): bool
    {
        return $user->can('couriers.update');
    }

    public function delete(User $user, Courier $courier): bool
    {
        return $user->can('couriers.delete');
    }
}
