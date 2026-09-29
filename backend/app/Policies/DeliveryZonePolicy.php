<?php

namespace App\Policies;

use App\Models\DeliveryZone;
use App\Models\User;

class DeliveryZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('delivery_zones.view');
    }

    public function view(User $user, DeliveryZone $deliveryZone): bool
    {
        return $user->can('delivery_zones.view');
    }

    public function create(User $user): bool
    {
        return $user->can('delivery_zones.create');
    }

    public function update(User $user, DeliveryZone $deliveryZone): bool
    {
        return $user->can('delivery_zones.update');
    }

    public function delete(User $user, DeliveryZone $deliveryZone): bool
    {
        return $user->can('delivery_zones.delete');
    }
}
