<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OfferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hiring.offer.create');
    }

    public function view(User $user, Offer $offer): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hiring.offer.create');
    }

    public function update(User $user, Offer $offer): bool
    {
        return $user->hasPermissionTo('hiring.offer.create');
    }

    public function send(User $user, Offer $offer): bool
    {
        return $user->hasPermissionTo('hiring.offer.send');
    }

    public function delete(User $user, Offer $offer): bool
    {
        return $user->hasPermissionTo('hiring.offer.create');
    }
}
