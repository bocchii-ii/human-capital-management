<?php

namespace App\Policies;

use App\Models\User;

class TenantPolicy
{
    public function viewDashboard(User $user): bool
    {
        return $user->can('reporting.dashboard.view');
    }
}
