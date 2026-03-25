<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Shipper;
use App\Models\User;

final class ShipperPolicy
{
    public function view(User $user, Shipper $shipper): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->staff()->exists()) {
            return true;
        }

        if ($user->shipper?->is($shipper)) {
            return true;
        }

        return false;
    }
}
