<?php

namespace App\Policies;

use App\Models\NotarialEntry;
use App\Models\User;

/**
 * Anyone can record an entry (the secretary does this); only administrators correct or remove one.
 */
class NotarialEntryPolicy
{
    public function update(User $user, NotarialEntry $entry): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, NotarialEntry $entry): bool
    {
        return $user->isAdmin();
    }
}
