<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('training.course.view');
    }

    public function view(User $user, Course $course): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }

    public function update(User $user, Course $course): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }

    public function publish(User $user, Course $course): bool
    {
        return $user->hasPermissionTo('training.course.publish');
    }
}
