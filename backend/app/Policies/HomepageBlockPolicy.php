<?php

namespace App\Policies;

use App\Models\HomepageBlock;
use App\Models\User;

/**
 * The granular builder.view/builder.edit/builder.publish permissions
 * already existed in RoleAndPermissionSeeder (wired to Marketing Manager —
 * view+edit, no publish — and Content Manager — all three) before this
 * phase was picked up, unlike Page's single pages.manage permission.
 * Reading/writing a block's settings is one thing; making it live on the
 * real storefront is a separate, more restricted ability — see
 * HomepageBlockController::publish()/unpublish().
 */
class HomepageBlockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('builder.view');
    }

    public function view(User $user, HomepageBlock $block): bool
    {
        return $user->can('builder.view');
    }

    public function create(User $user): bool
    {
        return $user->can('builder.edit');
    }

    public function update(User $user, HomepageBlock $block): bool
    {
        return $user->can('builder.edit');
    }

    public function delete(User $user, HomepageBlock $block): bool
    {
        return $user->can('builder.edit');
    }

    public function publish(User $user, HomepageBlock $block): bool
    {
        return $user->can('builder.publish');
    }
}
