<?php

namespace App\Policies;

use App\Models\NotarialEntry;
use App\Models\User;

/**
 * Anyone can record an entry (the secretary does this); only administrators correct or remove one.
 */
class NotarialEntryPolicy
{
    public function view(User $user, NotarialEntry $entry): bool
    {
        return (bool) $entry->document?->isVisibleTo($user);
    }

    /** A lawyer (notary) may correct an entry; only the administrator deletes one. */
    public function update(User $user, NotarialEntry $entry): bool
    {
        return $user->isLawyer();
    }

    public function delete(User $user, NotarialEntry $entry): bool
    {
        return $user->isAdmin();
    }
}
