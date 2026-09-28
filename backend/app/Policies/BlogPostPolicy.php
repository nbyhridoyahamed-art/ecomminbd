<?php

namespace App\Policies;

use App\Models\BlogPost;
use App\Models\User;

/**
 * One umbrella permission, the same PagePolicy shape — blog.manage already
 * exists in RoleAndPermissionSeeder (wired to Marketing Manager/SEO
 * Manager/Content Manager) waiting for a real blog to activate it. This
 * minimal BlogPost model borrows it now for the homepage builder's Blog
 * Posts block; Phase 14 (the real Blog CMS) is expected to take it over.
 */
class BlogPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('blog.manage');
    }

    public function view(User $user, BlogPost $post): bool
    {
        return $user->can('blog.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('blog.manage');
    }

    public function update(User $user, BlogPost $post): bool
    {
        return $user->can('blog.manage');
    }

    public function delete(User $user, BlogPost $post): bool
    {
        return $user->can('blog.manage');
    }
}
