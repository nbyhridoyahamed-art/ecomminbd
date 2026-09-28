<?php

namespace App\Policies;

use App\Models\BlogCategory;
use App\Models\User;

class BlogCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('blog.manage');
    }

    public function view(User $user, BlogCategory $category): bool
    {
        return $user->can('blog.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('blog.manage');
    }

    public function update(User $user, BlogCategory $category): bool
    {
        return $user->can('blog.manage');
    }

    public function delete(User $user, BlogCategory $category): bool
    {
        return $user->can('blog.manage');
    }
}
