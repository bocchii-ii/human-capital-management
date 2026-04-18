<?php

namespace App\Policies;

use App\Models\QuestionOption;
use App\Models\User;

class QuestionOptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('training.course.view');
    }

    public function view(User $user, QuestionOption $questionOption): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }

    public function update(User $user, QuestionOption $questionOption): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }

    public function delete(User $user, QuestionOption $questionOption): bool
    {
        return $user->hasPermissionTo('training.course.manage');
    }
}
