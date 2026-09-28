<?php

namespace App\Policies;

use App\Models\SeoTemplate;
use App\Models\User;

class SeoTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('seo.manage');
    }

    public function view(User $user, SeoTemplate $seoTemplate): bool
    {
        return $user->can('seo.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('seo.manage');
    }

    public function update(User $user, SeoTemplate $seoTemplate): bool
    {
        return $user->can('seo.manage');
    }

    public function delete(User $user, SeoTemplate $seoTemplate): bool
    {
        return $user->can('seo.manage');
    }
}
