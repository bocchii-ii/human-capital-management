<?php

namespace App\Policies;

use App\Models\Interview;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InterviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hiring.interview.schedule');
    }

    public function view(User $user, Interview $interview): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hiring.interview.schedule');
    }

    public function update(User $user, Interview $interview): bool
    {
        return $user->hasPermissionTo('hiring.interview.schedule');
    }

    public function delete(User $user, Interview $interview): bool
    {
        return $user->hasPermissionTo('hiring.interview.schedule');
    }
}
