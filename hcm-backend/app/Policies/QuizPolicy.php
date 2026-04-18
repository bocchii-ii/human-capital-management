<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;

class QuizPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('training.course.view');
    }

    public function view(User $user, Quiz $quiz): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }
}
