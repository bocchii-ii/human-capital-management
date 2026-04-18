<?php

namespace App\Policies;

use App\Models\CourseModule;
use App\Models\User;

class CourseModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('training.course.view');
    }

    public function view(User $user, CourseModule $module): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }

    public function update(User $user, CourseModule $module): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }

    public function delete(User $user, CourseModule $module): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }
}
