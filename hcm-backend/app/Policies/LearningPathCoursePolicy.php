<?php

namespace App\Policies;

use App\Models\LearningPathCourse;
use App\Models\User;

class LearningPathCoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('training.course.view');
    }

    public function view(User $user, LearningPathCourse $learningPathCourse): bool
    {
        return $user->can('training.course.view');
    }

    public function create(User $user): bool
    {
        return $user->can('training.course.manage');
    }

    public function update(User $user, LearningPathCourse $learningPathCourse): bool
    {
        return $user->can('training.course.manage');
    }

    public function delete(User $user, LearningPathCourse $learningPathCourse): bool
    {
        return $user->can('training.course.manage');
    }
}
