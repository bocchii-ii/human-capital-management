<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('training.course.view');
    }

    public function view(User $user, Lesson $lesson): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }
}
