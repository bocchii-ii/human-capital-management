<?php

namespace App\Policies;

use App\Models\Applicant;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ApplicantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hiring.application.view');
    }

    public function view(User $user, Applicant $applicant): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hiring.application.update');
    }

    public function update(User $user, Applicant $applicant): bool
    {
        return $user->hasPermissionTo('hiring.application.update');
    }

    public function delete(User $user, Applicant $applicant): bool
    {
        return $user->hasPermissionTo('hiring.application.update');
    }
}
