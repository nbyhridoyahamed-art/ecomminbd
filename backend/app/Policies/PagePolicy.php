<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * One umbrella permission, not the view/create/update/delete split
 * Category/Product use — pages.manage already exists in
 * RoleAndPermissionSeeder (wired to SEO Manager/Content Manager since
 * before this phase was picked up), the same single-permission shape
 * settings.manage already established for a low-stakes admin resource.
 */
class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pages.manage');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can('pages.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('pages.manage');
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can('pages.manage');
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can('pages.manage');
    }
}
