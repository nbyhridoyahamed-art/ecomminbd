<?php

namespace App\Policies;

use App\Models\ProductAttribute;
use App\Models\User;

class ProductAttributePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attributes.view');
    }

    public function view(User $user, ProductAttribute $attribute): bool
    {
        return $user->can('attributes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('attributes.create');
    }

    public function update(User $user, ProductAttribute $attribute): bool
    {
        return $user->can('attributes.update');
    }

    public function delete(User $user, ProductAttribute $attribute): bool
    {
        return $user->can('attributes.delete');
    }
}
