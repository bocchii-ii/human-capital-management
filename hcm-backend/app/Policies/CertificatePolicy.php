<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;

class CertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('training.course.view') || $user->can('training.enrollment.manage');
    }

    public function view(User $user, Certificate $certificate): bool
    {
        if ($user->can('training.enrollment.manage')) {
            return true;
        }

        return $user->can('training.course.view')
            && $user->employee?->id === $certificate->employee_id;
    }

    public function download(User $user, Certificate $certificate): bool
    {
        return $this->view($user, $certificate);
    }
}
