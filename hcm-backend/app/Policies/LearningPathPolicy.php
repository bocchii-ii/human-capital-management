<?php

namespace App\Policies;

use App\Models\LearningPath;
use App\Models\User;

class LearningPathPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('training.course.view');
    }

    public function view(User $user, LearningPath $learningPath): bool
    {
        return $user->can('training.course.view');
    }

    public function create(User $user): bool
    {
        return $user->can('training.course.manage');
    }

    public function update(User $user, LearningPath $learningPath): bool
    {
        return $user->can('training.course.manage');
    }

    public function delete(User $user, LearningPath $learningPath): bool
    {
        return $user->can('training.course.manage');
    }

    public function assign(User $user, LearningPath $learningPath): bool
    {
        return $user->can('training.enrollment.manage');
    }
}
