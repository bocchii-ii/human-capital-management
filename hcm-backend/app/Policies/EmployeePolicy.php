<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EmployeePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hr.employee.view') || $user->hasPermissionTo('hr.employee.manage');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->viewAny($user);
    }

    public function manage(User $user): bool
    {
        return $user->hasPermissionTo('hr.employee.manage');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->manage($user);
    }
}
