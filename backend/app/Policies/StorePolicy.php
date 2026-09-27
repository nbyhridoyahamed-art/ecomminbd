<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stores.view');
    }

    public function view(User $user, Store $store): bool
    {
        return $user->can('stores.view');
    }

    public function create(User $user): bool
    {
        return $user->can('stores.create');
    }

    public function update(User $user, Store $store): bool
    {
        return $user->can('stores.update');
    }

    public function delete(User $user, Store $store): bool
    {
        return $user->can('stores.delete');
    }
}
