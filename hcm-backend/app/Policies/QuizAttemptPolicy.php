<?php

namespace App\Policies;

use App\Models\QuizAttempt;
use App\Models\User;

class QuizAttemptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('training.enrollment.manage')
            || $user->hasPermissionTo('training.course.view');
    }

    public function view(User $user, QuizAttempt $attempt): bool
    {
        if ($user->hasPermissionTo('training.enrollment.manage')) {
            return true;
        }

        return $user->hasPermissionTo('training.course.view')
            && $user->employee?->id === $attempt->employee_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('training.course.view');
    }

    public function delete(User $user, QuizAttempt $attempt): bool
    {
        return $user->hasPermissionTo('training.enrollment.manage');
    }

    public function submit(User $user, QuizAttempt $attempt): bool
    {
        if ($user->hasPermissionTo('training.enrollment.manage')) {
            return true;
        }

        return $user->hasPermissionTo('training.course.view')
            && $user->employee?->id === $attempt->employee_id;
    }
}
