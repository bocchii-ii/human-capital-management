<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hiring.application.view');
    }

    public function view(User $user, Application $application): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hiring.application.update');
    }

    public function update(User $user, Application $application): bool
    {
        return $user->hasPermissionTo('hiring.application.update');
    }

    public function delete(User $user, Application $application): bool
    {
        return $user->hasPermissionTo('hiring.application.update');
    }
}
