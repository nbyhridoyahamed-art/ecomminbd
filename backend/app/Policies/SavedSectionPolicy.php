<?php

namespace App\Policies;

use App\Models\SavedSection;
use App\Models\User;

class SavedSectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('builder.view');
    }

    public function view(User $user, SavedSection $section): bool
    {
        return $user->can('builder.view');
    }

    public function create(User $user): bool
    {
        return $user->can('builder.edit');
    }

    public function update(User $user, SavedSection $section): bool
    {
        return $user->can('builder.edit');
    }

    public function delete(User $user, SavedSection $section): bool
    {
        return $user->can('builder.edit');
    }
}
