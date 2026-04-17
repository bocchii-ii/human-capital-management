<?php

namespace App\Policies;

use App\Models\JobRequisition;
use App\Models\User;

class JobRequisitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hiring.requisition.view');
    }

    public function view(User $user, JobRequisition $jobRequisition): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hiring.requisition.create');
    }

    public function update(User $user, JobRequisition $jobRequisition): bool
    {
        return $user->hasPermissionTo('hiring.requisition.edit');
    }

    public function approve(User $user, JobRequisition $jobRequisition): bool
    {
        return $user->hasPermissionTo('hiring.requisition.approve');
    }

    public function delete(User $user, JobRequisition $jobRequisition): bool
    {
        return $user->hasPermissionTo('hiring.requisition.delete');
    }
}
