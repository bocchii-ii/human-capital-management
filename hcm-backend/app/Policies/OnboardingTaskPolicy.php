<?php

namespace App\Policies;

use App\Models\OnboardingTask;
use App\Models\User;

class OnboardingTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('onboarding.template.view');
    }

    public function view(User $user, OnboardingTask $task): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('onboarding.template.manage');
    }

    public function update(User $user, OnboardingTask $task): bool
    {
        return $user->hasPermissionTo('onboarding.template.manage');
    }

    public function delete(User $user, OnboardingTask $task): bool
    {
        return $user->hasPermissionTo('onboarding.template.manage');
    }
}
