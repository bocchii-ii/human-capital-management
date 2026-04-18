<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('training.course.view') || $user->can('training.enrollment.manage');
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        if ($user->can('training.enrollment.manage')) {
            return true;
        }

        return $user->can('training.course.view')
            && $user->employee?->id === $enrollment->employee_id;
    }

    public function create(User $user): bool
    {
        return $user->can('training.course.view') || $user->can('training.enrollment.manage');
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->can('training.enrollment.manage');
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $user->can('training.enrollment.manage');
    }

    public function start(User $user, Enrollment $enrollment): bool
    {
        if ($user->can('training.enrollment.manage')) {
            return true;
        }

        return $user->can('training.course.view')
            && $user->employee?->id === $enrollment->employee_id;
    }

    public function withdraw(User $user, Enrollment $enrollment): bool
    {
        return $user->can('training.enrollment.manage');
    }

    public function completeLesson(User $user, Enrollment $enrollment): bool
    {
        if ($user->can('training.enrollment.manage')) {
            return true;
        }

        return $user->can('training.course.view')
            && $user->employee?->id === $enrollment->employee_id;
    }

    public function issueCertificate(User $user, Enrollment $enrollment): bool
    {
        return $user->can('training.enrollment.manage');
    }
}
