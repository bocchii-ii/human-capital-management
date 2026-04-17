<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PositionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr.department.manage') || $user->hasPermissionTo('hr.employee.view');
    }

    public function view(User $user, Position $position): bool
    {
        return $this->viewAny($user);
    }

    public function manage(User $user): bool
    {
        return $user->hasPermissionTo('hr.department.manage');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Position $position): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Position $position): bool
    {
        return $this->manage($user);
    }
}
