<?php

namespace App\Policies;

use App\Models\OnboardingAssignment;
use App\Models\User;

class OnboardingAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('onboarding.assignment.view');
    }

    public function view(User $user, OnboardingAssignment $assignment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('onboarding.assignment.manage');
    }

    public function update(User $user, OnboardingAssignment $assignment): bool
    {
        return $user->hasPermissionTo('onboarding.assignment.manage');
    }

    public function delete(User $user, OnboardingAssignment $assignment): bool
    {
        return $user->hasPermissionTo('onboarding.assignment.manage');
    }
}
