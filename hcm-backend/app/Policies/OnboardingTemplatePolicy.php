<?php

namespace App\Policies;

use App\Models\OnboardingTemplate;
use App\Models\User;

class OnboardingTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('onboarding.template.view');
    }

    public function view(User $user, OnboardingTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('onboarding.template.manage');
    }

    public function update(User $user, OnboardingTemplate $template): bool
    {
        return $user->hasPermissionTo('onboarding.template.manage');
    }

    public function delete(User $user, OnboardingTemplate $template): bool
    {
        return $user->hasPermissionTo('onboarding.template.manage');
    }
}
