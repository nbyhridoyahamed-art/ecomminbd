<?php

namespace App\Policies;

use App\Models\BlogPost;
use App\Models\User;

/**
 * One umbrella permission, the same PagePolicy shape — blog.manage already
 * existed in RoleAndPermissionSeeder (wired to Marketing Manager/SEO
 * Manager/Content Manager) from the homepage builder's minimal BlogPost
 * model; Phase 14's real Blog CMS reuses it as-is.
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
