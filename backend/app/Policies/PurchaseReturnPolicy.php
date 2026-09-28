<?php

namespace App\Policies;

use App\Models\PurchaseReturn;
use App\Models\User;

class PurchaseReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchase_returns.view');
    }

    public function view(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $user->can('purchase_returns.view');
    }

    public function create(User $user): bool
    {
        return $user->can('purchase_returns.create');
    }

    public function update(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $user->can('purchase_returns.update');
    }
}
