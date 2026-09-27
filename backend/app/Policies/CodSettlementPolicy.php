<?php

namespace App\Policies;

use App\Models\CodSettlement;
use App\Models\User;

class CodSettlementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cod_settlements.view');
    }

    public function view(User $user, CodSettlement $codSettlement): bool
    {
        return $user->can('cod_settlements.view');
    }

    public function create(User $user): bool
    {
        return $user->can('cod_settlements.create');
    }
}
