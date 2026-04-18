<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('training.course.view');
    }

    public function view(User $user, Question $question): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }

    public function update(User $user, Question $question): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }

    public function delete(User $user, Question $question): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }
}
