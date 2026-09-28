<?php

namespace App\Policies;

use App\Models\BlogTag;
use App\Models\User;

class BlogTagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('blog.manage');
    }

    public function view(User $user, BlogTag $tag): bool
    {
        return $user->can('blog.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('blog.manage');
    }

    public function update(User $user, BlogTag $tag): bool
    {
        return $user->can('blog.manage');
    }

    public function delete(User $user, BlogTag $tag): bool
    {
        return $user->can('blog.manage');
    }
}
