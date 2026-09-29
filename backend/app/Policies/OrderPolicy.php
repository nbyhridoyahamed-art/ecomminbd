<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can('orders.view');
    }

    public function create(User $user): bool
    {
        return $user->can('orders.create');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can('orders.update');
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->can('orders.cancel');
    }

    public function recordPayment(User $user, Order $order): bool
    {
        return $user->can('orders.record_payment');
    }
}
