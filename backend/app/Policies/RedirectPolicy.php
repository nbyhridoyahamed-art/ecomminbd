<?php

namespace App\Policies;

use App\Models\Redirect;
use App\Models\User;

/** Shares the seo.manage umbrella permission with SeoTemplate — same single-permission shape PagePolicy established. */
class RedirectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('seo.manage');
    }

    public function view(User $user, Redirect $redirect): bool
    {
        return $user->can('seo.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('seo.manage');
    }

    public function update(User $user, Redirect $redirect): bool
    {
        return $user->can('seo.manage');
    }

    public function delete(User $user, Redirect $redirect): bool
    {
        return $user->can('seo.manage');
    }
}
