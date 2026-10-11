<?php

namespace App\Policies;

use App\Models\ClientId;
use App\Models\User;

/** Administrators, or whoever added the ID, can remove it. */
class ClientIdPolicy
{
    public function delete(User $user, ClientId $clientId): bool
    {
        return $user->isAdmin() || (int) $clientId->created_by === $user->id;
    }
}
